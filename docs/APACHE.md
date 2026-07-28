# Esecuzione con Apache

Il progetto include `symfony/apache-pack` e il file `public/.htaccess` con le regole di riscrittura per il front controller Symfony.

## Configurazione richiesta

- Apache 2.4 o successivo;
- modulo `mod_rewrite` abilitato;
- `DocumentRoot` impostata sulla cartella `public` del progetto;
- `AllowOverride All` per la stessa cartella, altrimenti `.htaccess` viene ignorato;
- PHP 8.4 con le estensioni indicate nel README.

Esempio essenziale di VirtualHost:

```apache
<VirtualHost *:80>
    ServerName studiocommesse.local
    DocumentRoot "C:/Percorso/StudioCommesse/public"

    <Directory "C:/Percorso/StudioCommesse/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Dopo la modifica della configurazione, riavviare Apache. In produzione è preferibile trasferire le regole di rewrite direttamente nel VirtualHost e usare `AllowOverride None`; il file `.htaccess` resta utile per installazioni Apache condivise o locali.

## HTTPS e dati personali

Per dati personali ed economici usare un VirtualHost HTTPS con certificato valido e reindirizzare le richieste HTTP verso HTTPS. L’applicazione invia HSTS soltanto quando Symfony riconosce la richiesta come sicura. Se Apache termina TLS direttamente, non serve configurare proxy fidati; se TLS termina su un reverse proxy, configurare correttamente i trusted proxy prima di affidarsi a `X-Forwarded-Proto`.

Non pubblicare come alias o directory web `var`, `backups`, `config`, `vendor` o la radice del progetto. Il solo punto di ingresso web deve restare `public`.
