#!/bin/bash
# Verificación de requisitos para Moodle en el servidor
echo "======================================"
echo " Verificación de servidor para Moodle"
echo "======================================"
echo ""

echo "--- Sistema ---"
uname -a
echo ""

echo "--- PHP versión ---"
php -v 2>/dev/null || echo "PHP NO encontrado"
echo ""

echo "--- Extensiones PHP ---"
php -m 2>/dev/null || echo "No se pueden listar extensiones"
echo ""

echo "--- Configuración PHP relevante ---"
php -i 2>/dev/null | grep -E "memory_limit|max_input_vars|upload_max_filesize|post_max_size|max_execution_time" || echo "No se puede leer configuración PHP"
echo ""

echo "--- MySQL/MariaDB ---"
mysql --version 2>/dev/null || echo "MySQL CLI no disponible"
echo ""

echo "--- Git ---"
git --version 2>/dev/null || echo "Git NO encontrado"
echo ""

echo "--- Espacio en disco ---"
df -h /home/moralesbilma 2>/dev/null || df -h
echo ""

echo "--- Directorio public_html ---"
ls -la /home/moralesbilma/public_html/ 2>/dev/null
echo ""

echo "--- Subdominio virtual existente? ---"
if [ -d "/home/moralesbilma/public_html/virtual" ]; then
    echo "SI existe /home/moralesbilma/public_html/virtual"
    ls -la /home/moralesbilma/public_html/virtual/
else
    echo "NO existe /home/moralesbilma/public_html/virtual"
fi
echo ""

echo "--- PHP disponibles (CloudLinux) ---"
ls /opt/alt/php*/usr/bin/php 2>/dev/null || echo "No se encontraron versiones alternativas"
echo ""

echo "======================================"
echo " Verificación completa"
echo "======================================"
