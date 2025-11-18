@echo off
REM Script para eliminar BOM UTF-8 de Envio.php
REM Ejecutar: fix_bom.bat

echo ========================================
echo  Eliminando BOM UTF-8 de Envio.php
echo ========================================
echo.

powershell -Command "[System.IO.File]::WriteAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', [System.IO.File]::ReadAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', [System.Text.Encoding]::UTF8), (New-Object System.Text.UTF8Encoding $false))"

echo.
echo Verificando sintaxis PHP...
echo.

php -l c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php

echo.
echo ========================================
echo  Proceso completado
echo ========================================
pause
