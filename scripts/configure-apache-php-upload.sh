#!/usr/bin/env bash
set -euo pipefail

# Configura límites de subida para Apache + PHP.
# Uso:
#   sudo bash scripts/configure-apache-php-upload.sh
#   sudo bash scripts/configure-apache-php-upload.sh /etc/php/8.2/apache2/php.ini
#
# Variables opcionales por entorno:
#   UPLOAD_MAX_FILESIZE=256M
#   POST_MAX_SIZE=270M
#   MAX_EXECUTION_TIME=300
#   MAX_INPUT_TIME=300
#   MEMORY_LIMIT=512M

UPLOAD_MAX_FILESIZE="${UPLOAD_MAX_FILESIZE:-256M}"
POST_MAX_SIZE="${POST_MAX_SIZE:-270M}"
MAX_EXECUTION_TIME="${MAX_EXECUTION_TIME:-300}"
MAX_INPUT_TIME="${MAX_INPUT_TIME:-300}"
MEMORY_LIMIT="${MEMORY_LIMIT:-512M}"

if [[ "${EUID}" -ne 0 ]]; then
  echo "❌ Ejecuta este script con sudo/root."
  exit 1
fi

detect_apache_php_ini() {
  local latest_ini=""

  # Busca php.ini de Apache por versión instalada en /etc/php/*/apache2/php.ini
  while IFS= read -r -d '' candidate; do
    latest_ini="$candidate"
  done < <(find /etc/php -maxdepth 3 -type f -path '*/apache2/php.ini' -print0 2>/dev/null | sort -zV)

  if [[ -n "$latest_ini" ]]; then
    echo "$latest_ini"
    return 0
  fi

  return 1
}

PHP_INI_PATH="${1:-}"
if [[ -z "$PHP_INI_PATH" ]]; then
  if ! PHP_INI_PATH="$(detect_apache_php_ini)"; then
    echo "❌ No se encontró php.ini para Apache automáticamente."
    echo "   Pasa la ruta manualmente, por ejemplo:"
    echo "   sudo bash scripts/configure-apache-php-upload.sh /etc/php/8.2/apache2/php.ini"
    exit 1
  fi
fi

if [[ ! -f "$PHP_INI_PATH" ]]; then
  echo "❌ No existe el archivo: $PHP_INI_PATH"
  exit 1
fi

echo "📄 php.ini detectado: $PHP_INI_PATH"

BACKUP_PATH="${PHP_INI_PATH}.bak.$(date +%Y%m%d_%H%M%S)"
cp "$PHP_INI_PATH" "$BACKUP_PATH"
echo "💾 Backup creado en: $BACKUP_PATH"

set_ini_value() {
  local key="$1"
  local value="$2"
  local file="$3"

  if grep -Eq "^[;[:space:]]*${key}[[:space:]]*=" "$file"; then
    sed -ri "s|^[;[:space:]]*${key}[[:space:]]*=.*|${key} = ${value}|" "$file"
  else
    printf "\n%s = %s\n" "$key" "$value" >> "$file"
  fi
}

set_ini_value "upload_max_filesize" "$UPLOAD_MAX_FILESIZE" "$PHP_INI_PATH"
set_ini_value "post_max_size" "$POST_MAX_SIZE" "$PHP_INI_PATH"
set_ini_value "max_execution_time" "$MAX_EXECUTION_TIME" "$PHP_INI_PATH"
set_ini_value "max_input_time" "$MAX_INPUT_TIME" "$PHP_INI_PATH"
set_ini_value "memory_limit" "$MEMORY_LIMIT" "$PHP_INI_PATH"

echo "✅ Parámetros aplicados:"
echo "   upload_max_filesize = $UPLOAD_MAX_FILESIZE"
echo "   post_max_size      = $POST_MAX_SIZE"
echo "   max_execution_time = $MAX_EXECUTION_TIME"
echo "   max_input_time     = $MAX_INPUT_TIME"
echo "   memory_limit       = $MEMORY_LIMIT"

echo
if command -v systemctl >/dev/null 2>&1; then
  echo "🔁 Reiniciando Apache..."
  systemctl restart apache2
  echo "✅ Apache reiniciado."
else
  echo "⚠️ No se encontró systemctl. Reinicia Apache manualmente para aplicar cambios."
fi

echo
echo "ℹ️ Verifica valores cargados desde Apache con un phpinfo() o una ruta de diagnóstico web."
