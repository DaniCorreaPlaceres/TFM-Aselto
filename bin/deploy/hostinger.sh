#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

# Arguments contain only deployment identity, never credentials. All paths stay in this domain.
root=${1:?domain root required}
sha=${2:?commit required}
checksum=${3:?package checksum required}
php_bin=${PHP_BIN:-/opt/alt/php84/usr/bin/php}
[[ "$root" =~ ^/home/u[0-9]+/domains/[a-z0-9.-]+$ && "$sha" =~ ^[a-f0-9]{40}$ && "$checksum" =~ ^[a-f0-9]{64}$ ]] || exit 2
[[ "$(realpath "$root")" == "$root" ]] || exit 2
app="$root/private/aselto"
public="$root/public_html"
incoming="$root/private/incoming/$sha"
stage="$incoming/unpacked"
for path in "$root/private" "$app" "$public" "$incoming" "$app/var" "$app/var/share"; do
    [[ -d "$path" && ! -L "$path" && "$(realpath "$path")" == "$path" ]] || exit 2
done
for tool in rsync flock tar curl mariadb-dump; do command -v "$tool" >/dev/null; done
[[ -x "$php_bin" && -f "$app/.env.local" && -f "$app/config/jwt/private.pem" && -f "$public/.htaccess" ]] || exit 2
[[ ! -L "$app/.env.local" && ! -L "$app/config/jwt" && ! -L "$public/.htaccess" ]] || exit 2
"$php_bin" -r 'exit(PHP_VERSION_ID >= 80400 && extension_loaded("pdo_mysql") && extension_loaded("zip") ? 0 : 1);'
"$php_bin" "$incoming/prepare.php" unpack "$incoming/package.zip" "$sha" "$checksum" "$stage"
for directory in src config migrations templates translations resources assets vendor public openapi; do
    [[ ! -L "$app/$directory" ]] || exit 2
done
mkdir -p "$root/private/backups"
[[ ! -L "$root/private/backups" ]] || exit 2
# Take the process lock before creating temporary database credentials.
exec 8>"$root/private/deploy.lock"
flock -n 8 || { echo 'Otro despliegue está activo.'; exit 1; }
marker="$app/var/share/deployment.maintenance"
[[ ! -e "$marker" && ! -L "$app/var/share/deployment.lock" ]] || { echo 'Mantenimiento previo pendiente de resolver.'; exit 1; }
backup="$root/private/backups/deploy-$sha-$(date -u +%Y%m%dT%H%M%SZ)-$$"
mkdir -m 700 "$backup"
"$php_bin" "$incoming/prepare.php" database-config "$app" "$backup"
db_name=$(cat "$backup/database-name")

started=0
changed=0
finished=0
cleanup() {
    status=$?
    rm -f "$backup/database-client.cnf"
    if [[ "$finished" == 0 && "$started" == 1 ]]; then
        if [[ "$changed" == 0 ]]; then
            if [[ -f "$backup/public.htaccess" ]]; then install -m 644 "$backup/public.htaccess" "$public/.htaccess"; fi
            rm -f "$marker"
            echo 'Despliegue detenido antes de cambiar código/esquema; servicio anterior conservado.'
        else
            touch "$marker"
            chmod 600 "$marker"
            printf 'RewriteEngine On\nRewriteRule ^ - [R=503,L]\n' > "$public/.htaccess"
            chmod 644 "$public/.htaccess"
            echo "ERROR: mantenimiento conservado. Recuperación manual desde $backup; no ejecutar migraciones down."
        fi
    fi
    exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT TERM
touch "$marker"
chmod 600 "$marker"
started=1

# A manually installed guard is mandatory before the first automated deployment.
# This probes the PHP application, before installing the web-server maintenance rule.
origin=${DEPLOY_ORIGIN:-https://aselto.com}
[[ "$origin" =~ ^https://[a-z0-9.-]+$ ]] || exit 2
curl --silent --show-error --max-time 20 -D "$backup/guard-headers" -o /dev/null "$origin/salud"
grep -qi '^X-Aselto-Maintenance: 1' "$backup/guard-headers" || { echo 'Falta instalar/verificar el guard de despliegue.'; exit 1; }
exec 9>"$app/var/share/deployment.lock"
flock -w 120 9 || { echo 'Hay peticiones activas; no se cambia la versión.'; exit 1; }
cp "$public/.htaccess" "$backup/public.htaccess"
printf 'RewriteEngine On\nRewriteRule ^ - [R=503,L]\n' > "$public/.htaccess"
chmod 644 "$public/.htaccess"

# No web writes or security cron can run while the exclusive lock is held.
mariadb-dump --defaults-extra-file="$backup/database-client.cnf" --single-transaction --quick --skip-lock-tables --no-tablespaces --hex-blob --default-character-set=utf8mb4 --result-file="$backup/database.sql" "$db_name"
test -s "$backup/database.sql"
tar -czf "$backup/application.tar.gz" --exclude='aselto/var/cache' --exclude='aselto/var/log' -C "$root/private" aselto
tar -czf "$backup/public.tar.gz" -C "$root" public_html
tar -tzf "$backup/application.tar.gz" >/dev/null
tar -tzf "$backup/public.tar.gz" >/dev/null
sha256sum "$backup/database.sql" "$backup/application.tar.gz" "$backup/public.tar.gz" "$backup/public.htaccess" > "$backup/SHA256SUMS"
chmod 600 "$backup/"*
rm -f "$backup/database-client.cnf"

changed=1
for directory in src config migrations templates translations resources assets vendor public openapi; do
    [[ -d "$stage/app/$directory" ]] || continue
    rsync -a --delete --safe-links --chmod=D755,F644 --exclude='/jwt/' --exclude='/secrets/' --exclude='/reference.php' "$stage/app/$directory/" "$app/$directory/"
done
for file in .env composer.json composer.lock symfony.lock importmap.php; do install -m 644 "$stage/app/$file" "$app/$file"; done
install -m 755 "$stage/app/bin/console" "$app/bin/console"
mkdir -p "$app/docs/marca"
[[ ! -L "$app/docs" && ! -L "$app/docs/marca" ]] || exit 2
rsync -a --delete --chmod=D755,F644 "$stage/app/docs/marca/" "$app/docs/marca/"
rsync -a --delete --safe-links --chmod=D755,F644 --exclude='/.htaccess' --exclude='/.well-known/' "$stage/web/" "$public/"
cd "$app"
"$php_bin" bin/console cache:clear --env=prod --no-debug
"$php_bin" bin/console doctrine:migrations:migrate --env=prod --no-debug --no-interaction
"$php_bin" bin/console doctrine:schema:validate --env=prod --no-debug
"$php_bin" bin/console cache:clear --env=prod --no-debug
"$php_bin" bin/console app:version --env=prod --no-debug
install -m 600 "$stage/release.json" "$app/var/share/deployed-release.json"
install -m 644 "$stage/web/.htaccess" "$public/.htaccess"
rm "$marker"
flock -u 9

curl --fail --silent --show-error --max-time 30 "$origin/salud" > "$backup/health.json"
"$php_bin" -r '$data=json_decode(file_get_contents($argv[1]),true,8,JSON_THROW_ON_ERROR);exit(($data["estado"]??null)==="ok" && ($data["base_de_datos"]??null)==="ok" ? 0 : 1);' "$backup/health.json"
[[ "$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' "$origin/entrar")" == 200 ]]
[[ "$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' "$origin/api/v1/me")" == 401 ]]
finished=1
echo "Despliegue verificado: $sha. Copia privada: $backup"
