using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Net;
using System.Net.Sockets;
using System.Security.Principal;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;
using System.Runtime.InteropServices;
using System.Windows.Forms;

internal static class ConfiguradorSistemaPracticas
{
    private const string Dominio = "SistemaPracticas";
    private const string Inicio = "# BEGIN SistemaPracticas";
    private const string Fin = "# END SistemaPracticas";

    [DllImport("dnsapi.dll", EntryPoint = "DnsFlushResolverCache")]
    private static extern bool VaciarCacheDns();

    [STAThread]
    private static void Main()
    {
        Application.EnableVisualStyles();
        try { Configurar(); }
        catch (Exception ex)
        {
            MessageBox.Show(ex.Message, "Configuración de SistemaPracticas", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private static void Configurar()
    {
        if (!new WindowsPrincipal(WindowsIdentity.GetCurrent()).IsInRole(WindowsBuiltInRole.Administrator))
            throw new InvalidOperationException("Ejecuta el .exe y acepta el aviso de permisos de administrador.");

        string carpetaEjecutable = AppDomain.CurrentDomain.BaseDirectory.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar);
        string proyecto = EncontrarProyecto(carpetaEjecutable);

        string xampp = EncontrarXampp();
        string archivoHosts = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.System), "drivers", "etc", "hosts");
        string httpdConf = Path.Combine(xampp, "apache", "conf", "httpd.conf");
        string vhosts = Path.Combine(xampp, "apache", "conf", "extra", "httpd-vhosts.conf");
        string httpd = Path.Combine(xampp, "apache", "bin", "httpd.exe");
        foreach (string f in new[] { archivoHosts, httpdConf, vhosts, httpd })
            if (!File.Exists(f)) throw new FileNotFoundException("No se encontró el archivo requerido: " + f);

        int puerto = ObtenerPuerto(httpdConf);
        if (!PuertoActivo(puerto))
            throw new InvalidOperationException("Inicia Apache desde el panel de XAMPP y vuelve a abrir este .exe.");

        ConfigurarHosts(archivoHosts);
        ConfigurarVhost(vhosts, proyecto, puerto);
        HabilitarVhosts(httpdConf);

        string apache = Path.Combine(xampp, "apache");
        Resultado prueba = Ejecutar(httpd, "-d \"" + apache + "\" -f conf/httpd.conf -t", apache);
        if (prueba.Codigo != 0)
            throw new InvalidOperationException("Apache no aceptó la configuración. " + (prueba.Error + " " + prueba.Salida).Trim());

        if (!ReiniciarApache(httpd, apache, puerto))
            throw new InvalidOperationException("No se pudo reiniciar Apache. Reinícialo desde el panel de XAMPP y vuelve a ejecutar este .exe.");

        string url = puerto == 80 ? "http://" + Dominio + "/" : "http://" + Dominio + ":" + puerto + "/";
        if (!EsperarSitio(url))
            throw new InvalidOperationException("Apache se reinició, pero no respondió con la página de este proyecto en " + url + ". Revisa la configuración del VirtualHost.");
        Clipboard.SetText(url);
        MessageBox.Show("La URL se copió en el portapapeles:\r\n\r\n" + url, "Configuración lista", MessageBoxButtons.OK, MessageBoxIcon.Information);
    }

    private static string EncontrarXampp()
    {
        string[] opciones = { Environment.GetEnvironmentVariable("XAMPP_HOME"), @"C:\xampp" };
        foreach (string r in opciones)
            if (!String.IsNullOrEmpty(r) && File.Exists(Path.Combine(r, "apache", "conf", "httpd.conf"))) return r;

        using (FolderBrowserDialog selector = new FolderBrowserDialog())
        {
            selector.Description = "Selecciona la carpeta donde está instalado XAMPP";
            if (selector.ShowDialog() != DialogResult.OK) throw new OperationCanceledException("No se seleccionó la carpeta de XAMPP.");
            return selector.SelectedPath;
        }
    }

    private static string EncontrarProyecto(string carpetaEjecutable)
    {
        if (File.Exists(Path.Combine(carpetaEjecutable, "index.php"))) return carpetaEjecutable;
        foreach (string carpeta in Directory.GetDirectories(carpetaEjecutable))
            if (File.Exists(Path.Combine(carpeta, "index.php"))) return carpeta;
        DirectoryInfo padre = Directory.GetParent(carpetaEjecutable);
        if (padre != null)
        {
            if (File.Exists(Path.Combine(padre.FullName, "index.php"))) return padre.FullName;
            foreach (string carpeta in Directory.GetDirectories(padre.FullName))
                if (File.Exists(Path.Combine(carpeta, "index.php"))) return carpeta;
        }
        throw new FileNotFoundException("No encontré la carpeta del proyecto junto a este .exe. Coloca ambos al mismo nivel, con la carpeta del proyecto al lado.");
    }

    private static int ObtenerPuerto(string archivo)
    {
        Match m = Regex.Match(File.ReadAllText(archivo), @"(?im)^\s*Listen\s+(?:[^\s]+:)?(?<p>\d+)\b");
        int p;
        return m.Success && Int32.TryParse(m.Groups["p"].Value, out p) ? p : 80;
    }

    private static bool PuertoActivo(int puerto)
    {
        try
        {
            using (TcpClient c = new TcpClient())
            {
                IAsyncResult r = c.BeginConnect("127.0.0.1", puerto, null, null);
                using (r.AsyncWaitHandle) if (!r.AsyncWaitHandle.WaitOne(1200, false)) return false;
                c.EndConnect(r);
                return c.Connected;
            }
        }
        catch { return false; }
    }

    private static bool ReiniciarApache(string exe, string apache, int puerto)
    {
        Resultado r = Ejecutar(exe, "-d \"" + apache + "\" -f conf/httpd.conf -k restart", apache);
        if (r.Codigo == 0 && EsperarApache(puerto)) return true;

        // XAMPP may run Apache as a console process instead of a Windows service.
        List<Process> procesos = new List<Process>();
        foreach (Process p in Process.GetProcessesByName("httpd"))
        {
            try
            {
                if (String.Equals(Path.GetFullPath(p.MainModule.FileName), Path.GetFullPath(exe), StringComparison.OrdinalIgnoreCase))
                    procesos.Add(p);
                else p.Dispose();
            }
            catch { p.Dispose(); }
        }
        foreach (Process p in procesos) { try { p.Kill(); } catch { } }
        foreach (Process p in procesos) { try { p.WaitForExit(5000); } catch { } p.Dispose(); }

        ProcessStartInfo start = new ProcessStartInfo(exe, "-d \"" + apache + "\" -f conf/httpd.conf");
        start.WorkingDirectory = apache;
        start.UseShellExecute = false;
        start.CreateNoWindow = true;
        try { using (Process.Start(start)) { } } catch { return false; }
        return EsperarApache(puerto);
    }

    private static bool EsperarApache(int puerto)
    {
        for (int i = 0; i < 10; i++)
        {
            if (PuertoActivo(puerto)) return true;
            Thread.Sleep(1000);
        }
        return false;
    }

    private static bool EsperarSitio(string url)
    {
        for (int intento = 0; intento < 8; intento++)
        {
            try
            {
                HttpWebRequest solicitud = (HttpWebRequest)WebRequest.Create(url);
                solicitud.AllowAutoRedirect = false;
                solicitud.Proxy = null;
                solicitud.Timeout = 2500;
                using (HttpWebResponse respuesta = (HttpWebResponse)solicitud.GetResponse())
                {
                    string destino = respuesta.Headers["Location"] ?? "";
                    if ((int)respuesta.StatusCode >= 300 && (int)respuesta.StatusCode < 400 && destino.Contains("/public/login.html")) return true;
                }
            }
            catch (WebException ex)
            {
                if (ex.Response != null) ex.Response.Close();
            }
            Thread.Sleep(750);
        }
        return false;
    }

    private static void ConfigurarHosts(string archivo)
    {
        List<string> nuevas = new List<string>();
        bool existe = false, cambio = false;
        foreach (string linea in File.ReadAllLines(archivo))
        {
            string limpia = linea.Trim();
            if (limpia.Length == 0 || limpia.StartsWith("#")) { nuevas.Add(linea); continue; }
            string[] partes = limpia.Split((char[])null, StringSplitOptions.RemoveEmptyEntries);
            int i = Array.FindIndex(partes, 1, s => String.Equals(s, Dominio, StringComparison.OrdinalIgnoreCase));
            if (partes.Length > 1 && i >= 1)
            {
                if (partes[0] == "127.0.0.1") { existe = true; nuevas.Add(linea); }
                else
                {
                    cambio = true;
                    string[] restantes = partes.Where((s, n) => n != i).ToArray();
                    if (restantes.Length > 1) nuevas.Add(String.Join(" ", restantes));
                }
            }
            else nuevas.Add(linea);
        }
        if (!existe) { nuevas.Add("127.0.0.1 " + Dominio); cambio = true; }
        if (cambio)
        {
            Backup(archivo);
            File.WriteAllLines(archivo, nuevas.ToArray(), Encoding.Default);
            try { VaciarCacheDns(); } catch { }
        }
    }

    private static void ConfigurarVhost(string archivo, string proyecto, int puerto)
    {
        string ruta = proyecto.Replace('\\', '/');
        string bloque = Inicio + Environment.NewLine +
            "<VirtualHost *:" + puerto + ">" + Environment.NewLine +
            "    ServerName " + Dominio + Environment.NewLine +
            "    DocumentRoot \"" + ruta + "\"" + Environment.NewLine +
            "    <Directory \"" + ruta + "\">" + Environment.NewLine +
            "        AllowOverride All" + Environment.NewLine +
            "        Require all granted" + Environment.NewLine +
            "    </Directory>" + Environment.NewLine +
            "</VirtualHost>" + Environment.NewLine + Fin;
        string original = File.ReadAllText(archivo);
        string patron = @"(?s)# BEGIN SistemaPracticas(?:\.UMG)?.*?# END SistemaPracticas(?:\.UMG)?";
        string nuevo = Regex.IsMatch(original, patron)
            ? Regex.Replace(original, patron, delegate { return bloque; })
            : original.TrimEnd() + Environment.NewLine + Environment.NewLine + bloque + Environment.NewLine;
        if (nuevo != original) { Backup(archivo); File.WriteAllText(archivo, nuevo, Encoding.Default); }
    }

    private static void HabilitarVhosts(string archivo)
    {
        string original = File.ReadAllText(archivo);
        const string linea = "Include conf/extra/httpd-vhosts.conf";
        string nuevo = Regex.Replace(original, @"(?im)^\s*#\s*Include\s+conf/extra/httpd-vhosts\.conf\s*$", linea);
        if (!Regex.IsMatch(nuevo, @"(?im)^\s*Include\s+conf/extra/httpd-vhosts\.conf\s*$"))
            nuevo = nuevo.TrimEnd() + Environment.NewLine + linea + Environment.NewLine;
        if (nuevo != original) { Backup(archivo); File.WriteAllText(archivo, nuevo, Encoding.Default); }
    }

    private static void Backup(string archivo)
    {
        string respaldo = archivo + ".SistemaPracticas.bak";
        if (!File.Exists(respaldo)) File.Copy(archivo, respaldo);
    }

    private static Resultado Ejecutar(string exe, string args, string directorio)
    {
        ProcessStartInfo i = new ProcessStartInfo(exe, args);
        i.WorkingDirectory = directorio;
        i.UseShellExecute = false;
        i.CreateNoWindow = true;
        i.RedirectStandardOutput = true;
        i.RedirectStandardError = true;
        using (Process p = Process.Start(i))
        {
            string salida = p.StandardOutput.ReadToEnd();
            string error = p.StandardError.ReadToEnd();
            p.WaitForExit();
            return new Resultado(p.ExitCode, salida, error);
        }
    }

    private sealed class Resultado
    {
        public readonly int Codigo;
        public readonly string Salida;
        public readonly string Error;
        public Resultado(int codigo, string salida, string error) { Codigo = codigo; Salida = salida; Error = error; }
    }
}
