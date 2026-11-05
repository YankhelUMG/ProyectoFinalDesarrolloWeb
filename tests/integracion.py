"""Verifica el contrato HTTP con MariaDB temporal. No usa la base de trabajo.

Requiere una instancia MySQL/MariaDB de pruebas ya iniciada en el puerto 3317.
Las tablas se recrean en refactor_pruebas, nunca en sistema_practicas.
"""
import argparse
import http.cookiejar
import json
import os
from pathlib import Path
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
DATABASE = 'refactor_pruebas'
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--php', default='C:/xampp/php/php.exe')
parser.add_argument('--mysql', default='C:/xampp/mysql/bin/mysql.exe')
parser.add_argument('--port', type=int, default=3317)
parser.add_argument('--http-port', type=int, default=8097)
parser.add_argument('--private-dir', required=True, help='Carpeta temporal fuera de htdocs')
parser.add_argument('--mysql-data', required=True, help='Directorio de instancia temporal creada con mysql_install_db')
parser.add_argument('--esperar', action='store_true', help='Mantener el servidor para revisión visual; crear terminar.txt en private-dir para cerrarlo')
args = parser.parse_args()
private = Path(args.private_dir).resolve()
if args.port == 3306:
    parser.error('Usa una instancia temporal; el puerto 3306 está excluido.')
private.mkdir(parents=True, exist_ok=True)
database_server = None
if args.mysql_data:
    import socket
    database_dir = Path(args.mysql_data).resolve()
    database_server = subprocess.Popen([str(Path(args.mysql).with_name('mysqld.exe')),
        f'--defaults-file={database_dir / "my.ini"}', '--bind-address=127.0.0.1',
        f'--port={args.port}', '--standalone'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
    for _ in range(100):
        try:
            with socket.create_connection(('127.0.0.1', args.port), timeout=.1):
                break
        except OSError:
            time.sleep(.1)
    else:
        database_server.terminate()
        raise RuntimeError('No se pudo iniciar la instancia temporal de MariaDB.')


def sql(source):
    subprocess.run([args.mysql, '--protocol=TCP', '-h127.0.0.1', f'-P{args.port}',
                    '-uroot', '--default-character-set=utf8mb4'],
                   input=source.encode('utf-8'), check=True, stdout=subprocess.DEVNULL)


# Confirma que la conexión pertenece al servidor temporal antes de preparar datos.
actual = subprocess.check_output([args.mysql, '--protocol=TCP', '-h127.0.0.1', f'-P{args.port}',
    '-uroot', '--skip-column-names', '-e', 'SELECT @@datadir;'], text=True).strip()
if Path(actual).resolve() != database_dir:
    if database_server.poll() is None:
        database_server.terminate()
    raise RuntimeError('La conexión no corresponde al directorio temporal indicado.')
sql(f'DROP DATABASE IF EXISTS {DATABASE};')
source = (ROOT / 'database/instalacion_completa.sql').read_text(encoding='utf-8-sig')
sql(source.replace('sistema_practicas', DATABASE))

# PDF pequeño para probar las cargas y las descargas del sistema real.
pdf = b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n'
(private / 'demo').mkdir(exist_ok=True)
for name in ['andrea', 'bruno', 'camila', 'diego', 'elena', 'fabian']:
    (private / 'demo' / f'cv_{name}.pdf').write_bytes(pdf)

env = dict(os.environ, DATABASE_HOST=f'127.0.0.1;port={args.port}',
           DATABASE_NAME=DATABASE, DATABASE_USER='root', DATABASE_PASSWORD='test',
           PRIVATE_UPLOAD_DIR=str(private), APP_TIMEZONE='America/Guatemala')
# La instalación temporal usa root sin clave. Se da una clave solo en esa instancia.
sql("ALTER USER 'root'@'localhost' IDENTIFIED BY 'test';")
log = (private / 'php.log').open('w', encoding='utf-8')
server = subprocess.Popen([args.php, '-S', f'127.0.0.1:{args.http_port}', '-t', str(ROOT),
                           str(ROOT / 'tests/router.php')], cwd=ROOT, env=env,
                          stdout=log, stderr=log)
base = f'http://127.0.0.1:{args.http_port}/public/api/'
checks = 0


def check(condition, description):
    global checks
    assert condition, description
    checks += 1
    print('OK', description, flush=True)


class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = None

    def request(self, method, path, data=None, status=200, token=True, file=False):
        headers = {}
        body = None
        if file:
            boundary = 'PruebasFormularioSeguro'
            parts = []
            for key, value in (data or {}).items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            parts.extend([f'--{boundary}\r\nContent-Disposition: form-data; name="cv"; filename="prueba.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode(),
                          pdf, f'\r\n--{boundary}--\r\n'.encode()])
            body = b''.join(parts)
            headers['Content-Type'] = f'multipart/form-data; boundary={boundary}'
        elif data is not None:
            body = json.dumps(data).encode()
            headers['Content-Type'] = 'application/json'
        if token and self.csrf:
            headers['X-CSRF-Token'] = self.csrf
        request = urllib.request.Request(base + urllib.parse.quote(path, safe='/?=&%'), body, headers, method=method)
        try:
            response = self.opener.open(request, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        raw = response.read()
        assert response.status == status, f'{method} {path}: {response.status} {raw[:300]!r}'
        if 'application/pdf' in response.headers.get('Content-Type', ''):
            check(raw.startswith(b'%PDF-'), 'Descarga de CV autorizada')
            return raw
        result = json.loads(raw)
        assert set(result) == {'ok', 'mensaje', 'datos', 'errores'}, result
        if isinstance(result['datos'], dict) and result['datos'].get('csrf_token'):
            self.csrf = result['datos']['csrf_token']
        return result['datos']

    def login(self, name):
        self.request('GET', 'csrf')
        return self.request('POST', 'auth/login', {'identificador': name, 'contrasena': 'ClaveDemo2026!'})


try:
    for _ in range(30):
        try:
            urllib.request.urlopen(base + 'csrf', timeout=1).read()
            break
        except urllib.error.URLError:
            time.sleep(.1)
    anon = Client()
    for page in ['/', '/public/login.html', '/public/practicas.php', '/pages/practicas.php',
                 '/pages/perfil.php', '/pages/empresa.php', '/pages/postulaciones.php',
                 '/pages/administracion.php', '/pages/usuarios.php', '/pages/consultas.php']:
        with urllib.request.urlopen(f'http://127.0.0.1:{args.http_port}' + page, timeout=10) as response:
            html = response.read().decode('utf-8')
            assert response.status == 200 and '<!doctype html>' in html.lower(), page
            assert 'Warning:' not in html and 'Fatal error:' not in html, page
    check(True, 'Pantallas con rutas planas y recursos de entrada disponibles')
    check(bool(anon.request('GET', 'csrf')['csrf_token']), 'GET csrf y cookie inicial')
    check(bool(anon.request('GET', 'catalogos')), 'Catálogos públicos')
    anon.request('GET', 'sistema')
    anon.request('GET', 'auth/me', status=401)
    anon.request('POST', 'auth/login', {}, status=403, token=False)
    anon.request('POST', 'auth/login', {'identificador': 'nadie', 'contrasena': 'incorrecta'}, status=401)
    check(True, 'Sesión y CSRF obligatorios')
    student, company, admin, root, pending = [Client() for _ in range(5)]
    for client, name, role in [(student, 'bruno', 'estudiante'), (company, 'innovatech', 'empresa'),
                               (admin, 'admin1', 'admin'), (root, 'superadmin', 'superadmin')]:
        check(client.login(name)['rol_codigo'] == role, f'Login y rol {role}')
        client.request('GET', 'auth/me')
        client.request('GET', 'perfil')
        client.request('GET', 'dashboard')
    pending.login('empresa_pendiente')
    pending.request('GET', 'perfil')
    pending.request('POST', 'practicas', {}, status=403)
    student.request('GET', 'admin/usuarios', status=403)
    check(True, 'Aislamiento de roles y empresa pendiente')
    catalogs = anon.request('GET', 'catalogos')
    area = next(x['id'] for x in catalogs if x['tipo'] == 'area')
    mode = next(x['id'] for x in catalogs if x['tipo'] == 'modalidad')
    practice = dict(titulo='Práctica de integración', descripcion='Propuesta creada por las pruebas',
                    requisitos='PHP y SQL', id_area=area, id_modalidad=mode, ubicacion='Guatemala',
                    fecha_inicio='2099-12-01', cupo_maximo=2, fecha_limite_postulacion='2099-11-01')
    practice_id = company.request('POST', 'practicas', practice, status=201)['id_practica']
    check(bool(practice_id), 'Empresa envía práctica a revisión')
    company.request('PUT', f'practicas/{practice_id}', practice)
    admin.request('PUT', f'practicas/{practice_id}/revision', {'estado_codigo': 'publicada'}, status=403)
    root.request('PUT', f'practicas/{practice_id}/revision', {'estado_codigo': 'publicada'})
    check(any(p['id_practica'] == practice_id for p in anon.request('GET', 'practicas?q=integración')['elementos']), 'Práctica publicada en catálogo')
    anon.request('GET', f'practicas/{practice_id}')
    student.request('GET', 'perfil/cv')
    application_id = student.request('POST', 'postulaciones', {'id_practica': practice_id, 'carta_presentacion': 'Quiero aprender.'}, status=201)['id_postulacion']
    check(bool(application_id), 'Estudiante se postula con CV')
    student.request('POST', 'postulaciones', {'id_practica': practice_id}, status=422)
    student.request('GET', f'postulaciones/{application_id}/historial')
    company.request('GET', f'practicas/{practice_id}/postulaciones')
    company.request('GET', f'cv/{application_id}')
    company.request('PUT', f'postulaciones/{application_id}', {'estado_codigo': 'aceptada'})
    check(student.request('GET', f'postulaciones/{application_id}')['estado_codigo'] == 'aceptada', 'Empresa acepta y estudiante consulta estado')
    student.request('DELETE', f'postulaciones/{application_id}')
    check(student.request('GET', f'postulaciones/{application_id}')['estado_codigo'] == 'retirada', 'Retiro conserva historial')
    student.request('POST', 'postulaciones', {'id_practica': practice_id}, status=201)
    company.request('PUT', f'postulaciones/{application_id}', {'estado_codigo': 'rechazada', 'comentario': 'No cumple los requisitos solicitados.'})
    for route in ['admin/reportes', 'admin/usuarios', 'admin/empresas', 'admin/catalogos/areas',
                  'admin/configuracion', 'admin/bitacora', 'reportes']:
        root.request('GET', route)
    check(True, 'Consultas administrativas, configuración y bitácora')
    for method, route in [('PUT', 'csrf'), ('DELETE', 'practicas'), ('POST', 'reportes')]:
        root.request(method, route, status=405)
    check(True, 'Se conservan los métodos HTTP permitidos')
    managed = root.request('POST', 'admin/usuarios', {
        'usuario': 'admin_test', 'correo': 'admin.test@example.com', 'nombres': 'Admin',
        'apellidos': 'Prueba', 'telefono': '55550099', 'contrasena': 'Pruebas2026!', 'rol_codigo': 'admin'
    }, status=201)['id_usuario']
    root.request('PUT', f'admin/usuarios/{managed}', {'correo': 'admin.test@example.com',
        'nombres': 'Admin editado', 'apellidos': 'Prueba', 'telefono': '55550099'})
    temporary = root.request('POST', f'admin/usuarios/{managed}/restablecer-clave')['contrasena_temporal']
    temporary_client = Client()
    temporary_client.request('GET', 'csrf')
    forced = temporary_client.request('POST', 'auth/login', {'identificador': 'admin_test', 'contrasena': temporary})
    check(bool(forced['debe_cambiar_password']), 'Restablecer clave exige cambio al ingresar')
    temporary_client.request('POST', 'auth/cambiar-clave', {'contrasena_actual': temporary, 'contrasena_nueva': 'NuevaClave2026!', 'confirmacion': 'NuevaClave2026!'})
    root.request('PUT', f'admin/usuarios/{managed}/estado', {'estado_codigo': 'inactivo', 'comentario': 'Desactivación de prueba.'})
    root.request('DELETE', f'admin/usuarios/{managed}')
    root.request('POST', f'admin/usuarios/{managed}/restaurar')
    check(True, 'Administración conserva edición, desactivación, baja lógica y restauración')
    user_data = dict(usuario='registro_prueba', correo='registro@example.com', nombres='Prueba', apellidos='Integración',
                     telefono='55551234', contrasena='Pruebas2026!', carnet='TEST-2026', semestre='Sexto',
                     universidad='Universidad de prueba', id_carrera=next(x['id'] for x in catalogs if x['tipo'] == 'carrera'))
    created = anon.request('POST', 'auth/registro-estudiante', user_data, status=201, file=True)
    check(created['rol_codigo'] == 'estudiante', 'Registro de estudiante con carga PDF')
    anon.request('GET', 'perfil/cv')
    anon.request('PUT', 'perfil', {k: v for k, v in user_data.items() if k not in ['contrasena', 'usuario']})
    anon.request('POST', 'perfil/cv', {}, file=True)
    anon.request('DELETE', 'perfil/cv')
    anon.request('GET', 'perfil/cv', status=404)
    check(True, 'Editar perfil, reemplazar y eliminar CV')
    company_data = dict(usuario='empresa_test', correo='empresa@example.com', contrasena='Pruebas2026!',
                        nombres='Contacto', apellidos='Prueba', nombre_legal='Empresa pruebas', correo_contacto='contacto@example.com')
    company_new = Client()
    company_new.request('GET', 'csrf')
    company_new.request('POST', 'auth/registro-empresa', company_data, status=201)
    check(True, 'Registro de empresa pendiente')
    companies = root.request('GET', 'admin/empresas')
    new_company = next(c for c in companies if c['nombre_legal'] == 'Empresa pruebas')
    root.request('PUT', f"admin/empresas/{new_company['id_empresa']}/revision", {'estado_codigo': 'aprobada', 'comentario': 'Aprobación de prueba.'})
    company_new.request('POST', 'auth/login', {'identificador': 'empresa_test', 'contrasena': 'Pruebas2026!'})
    check(bool(company_new.request('GET', 'practicas/mias') == []), 'Empresa aprobada habilita sus herramientas')
    check(anon.request('GET', 'auth.php')['rol_codigo'] == 'estudiante', 'Enlace PHP anterior usa sesión y permisos actuales')
    student.request('DELETE', 'auth/logout')
    student.request('GET', 'auth/me', status=401)
    check(True, 'Logout revoca la sesión')
    print(f'Completado: {checks} verificaciones de integración.', flush=True)
    if args.esperar:
        marker = private / 'terminar.txt'
        marker.unlink(missing_ok=True)
        print(f'Servidor para revisión visual: http://127.0.0.1:{args.http_port}', flush=True)
        while not marker.exists():
            time.sleep(.5)
        marker.unlink()
finally:
    server.terminate()
    server.wait(timeout=10)
    log.close()
    # Solo se restablece la cuenta de la instancia temporal, nunca la instalada.
    subprocess.run([args.mysql, '--protocol=TCP', '-h127.0.0.1', f'-P{args.port}', '-uroot', '-ptest',
                    '-e', "ALTER USER 'root'@'localhost' IDENTIFIED BY '';"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    if database_server:
        database_server.terminate()
        database_server.wait(timeout=10)

