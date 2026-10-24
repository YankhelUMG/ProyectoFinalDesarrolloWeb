<?php
declare(strict_types=1);
require_once __DIR__ . '/api/core/App.php';
$user = require_role('estudiante');
$pdo = db();
$stmt = $pdo->prepare('SELECT u.telefono,e.cv_nombre,e.carnet,e.carrera,e.semestre,e.universidad FROM usuarios u JOIN estudiantes e ON e.id_usuario=u.id_usuario WHERE u.id_usuario=:id');
$stmt->execute(['id'=>$user['id']]); $profile=$stmt->fetch();
page_start('Mi perfil');
echo '<section class="auth-panel"><div><span class="eyebrow">DATOS DE POSTULACIÓN</span><h1>Mi perfil</h1><p>Completa tus datos académicos, teléfono y CV para que las empresas puedan evaluar tu postulación.</p></div><form method="post" enctype="multipart/form-data" action="'.h(app_url('api/perfil.php')).'">'.csrf_field().'<label>Carné universitario<input name="carnet" maxlength="40" value="'.h($profile['carnet']??'').'" required></label><label>Carrera<input name="carrera" maxlength="150" value="'.h($profile['carrera']??'').'" required></label><label>Semestre<input name="semestre" maxlength="40" value="'.h($profile['semestre']??'').'"></label><label>Universidad<input name="universidad" maxlength="180" value="'.h($profile['universidad']??'Universidad Mariano Gálvez').'" required></label><label>Teléfono<input name="telefono" maxlength="25" value="'.h($profile['telefono']??'').'" autocomplete="tel" required></label><label>CV (Word .doc/.docx o PDF, máximo 5 MB)<input type="file" name="cv" accept=".pdf,.doc,.docx"'.(empty($profile['cv_nombre'])?' required':'').'></label>'.(!empty($profile['cv_nombre'])?'<p>CV guardado: '.h($profile['cv_nombre']).'</p>':'').'<button class="button button-primary" type="submit">Guardar perfil</button></form></section>';
page_end();
