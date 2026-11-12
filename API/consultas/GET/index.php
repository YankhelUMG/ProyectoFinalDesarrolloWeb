<?php
switch ($this->ruta[0] ?? '') {
    case 'cv': $this->descargarCv();
    case 'reportes': $this->reportes();
    case 'dashboard': $this->dashboard();
}
