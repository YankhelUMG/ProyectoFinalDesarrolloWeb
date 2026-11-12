<?php
if (($this->ruta[0] ?? '') === 'auth') $this->autenticacion();
if (($this->ruta[0] ?? '') === 'catalogos') $this->catalogos();
