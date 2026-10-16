#{"rootDirectory":"backend/public","phpVersion":"8.3"}
# Sancta — modelo de vhost do CloudPanel, cadastrado pelo instalar-sancta.sh como "Sancta".
# Parte do modelo oficial "Laravel 12" do CloudPanel, com estas diferenças:
#   - só a Cloudflare acessa o site (lista em /etc/nginx/cloudflare/allow.conf, atualizada toda semana);
#   - só o index.php executa PHP e arquivos ocultos (.env, .git) nunca são servidos;
#   - arquivo estático inexistente cai no Laravel (o JavaScript do Livewire vem de uma rota);
#   - uploads de até 64 MB (áudios do painel) e erros do PHP repassados como estão (JSON da API).
server {
  listen 80;
  listen [::]:80;
  listen 443 ssl http2;
  listen [::]:443 ssl http2;
  {{ssl_certificate_key}}
  {{ssl_certificate}}
  {{server_name}}
  {{root}}

  {{nginx_access_log}}
  {{nginx_error_log}}

  # Aqui o IP da conexão é o da Cloudflare, e é ele que a trava abaixo confere (se o servidor
  # trocar o IP pelo do visitante em outro lugar, essa troca fica desligada neste site). O Laravel
  # lê o IP do visitante no X-Forwarded-For, com TRUSTED_PROXIES=cloudflare no .env.
  set_real_ip_from 127.0.0.1;
  include /etc/nginx/cloudflare/allow.conf;

  if ($scheme != "https") {
    rewrite ^ https://$host$request_uri permanent;
  }

  location ~ /.well-known {
    auth_basic off;
    allow all;
  }

  {{settings}}

  index index.php;
  charset utf-8;
  client_max_body_size 64M;

  location ~ /\.(?!well-known) {
    deny all;
  }

  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }

  location = /index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param HTTPS $fastcgi_https;
    fastcgi_param HTTP_PROXY "";
    fastcgi_hide_header X-Powered-By;
    fastcgi_intercept_errors off;
    fastcgi_read_timeout 130s;
    fastcgi_send_timeout 130s;
    fastcgi_pass 127.0.0.1:{{php_fpm_port}};
    fastcgi_param PHP_VALUE "{{php_settings}}";
  }

  location ~ \.php$ {
    return 404;
  }

  location ~* \.(?:css|js|mjs|map|svg|png|jpe?g|gif|webp|avif|ico|woff2?|ttf|otf|mp3|m4a|aac|ogg|webmanifest)$ {
    try_files $uri /index.php?$query_string;
    expires 30d;
    add_header X-Content-Type-Options nosniff always;
    access_log off;
  }
}
