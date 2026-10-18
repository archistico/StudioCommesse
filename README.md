# Studio Commesse

Studio Commesse è un gestionale web interno per studi tecnici. Centralizza clienti, commesse, attività, ore, spese, incassi e documenti, con accessi distinti per Soci e Collaboratori.

L’applicazione usa Symfony, Doctrine ORM, SQLite, Twig e un’interfaccia Tabler responsive. Include report mensili, audit operativo, backup coordinati e strumenti per installazione e aggiornamento controllati.

## Funzioni principali

- gestione di clienti, commesse, responsabili, attività, priorità e scadenze;
- registrazione ore manuale o tramite timer, con riepiloghi per persona e commessa;
- preventivi, tariffe, spese, incassi e controllo della chiusura economica;
- documenti protetti associati a commesse e attività;
- report mensile, permessi per ruolo, registro audit filtrabile riservato ai Soci, blocco temporaneo del login, backup e ripristino.

## Requisiti

- PHP 8.4 o successivo;
- estensioni PHP `ctype`, `fileinfo`, `iconv`, `mbstring`, `PDO` e `pdo_sqlite`;
- Composer;
- Node.js 20 o successivo e npm;
- PowerShell per gli script Windows inclusi nel progetto;
- per Apache: Apache 2.4+, `mod_rewrite`, `DocumentRoot` sulla cartella `public` e `AllowOverride All`.

Per verificare la configurazione PHP usata dal terminale:

```powershell
php --ini
php -m
```

## Installazione

Scegliere la procedura [Windows](#windows-con-powershell) oppure la [guida Linux per la produzione](#linux-in-produzione-via-ssh). I comandi Linux seguenti sono per Bash, non per PowerShell.

### Windows con PowerShell

Estrarre il pacchetto in una cartella vuota ed eseguire:

```powershell
.\scripts\setup.ps1
```

Lo script controlla i requisiti, crea `.env.local`, installa le dipendenze, compila gli asset, applica le migrazioni e guida la creazione del primo Socio.

### Linux in produzione via SSH

L’esempio completo usa **Debian 13**, **Apache 2.4**, **PHP 8.4-FPM**, SQLite su disco locale e l’utente web `www-data`. Su Ubuntu o altre distribuzioni verificare prima disponibilità e nomi dei pacchetti, versione PHP, socket FPM e utente del web server: installare il pacchetto generico `php` non garantisce PHP 8.4 o successivo.

Occorrono un account SSH non root con accesso `sudo`, accesso al repository e un dominio con DNS già diretto al server. Gli esempi assumono un server dedicato all’applicazione; su un server condiviso adattare VirtualHost, configurazione PHP e riavvii senza interrompere gli altri siti.

Sostituire `deploy`, `IP_DEL_SERVER`, `commesse.example.com` e `admin@example.com` con i propri valori. La cartella usata in tutta la guida è `/var/www/studiocommesse`; se la si cambia, aggiornare anche VirtualHost, backup e comandi successivi. I comandi dopo SSH si eseguono **sul server**, uno alla volta, proseguendo soltanto se il precedente termina senza errori.

#### 1. Collegarsi e installare i pacchetti

Dal proprio computer:

```bash
ssh deploy@IP_DEL_SERVER
```

Sul server verificare la distribuzione e installare i componenti:

```bash
cat /etc/os-release
sudo apt update
sudo apt install -y git ca-certificates curl unzip acl nano apache2 \
  php8.4-cli php8.4-fpm php8.4-common php8.4-sqlite3 \
  php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-opcache \
  composer nodejs npm certbot python3-certbot-apache
php -v
php --ini
php -m
composer --version
node --version
npm --version
```

Servono PHP almeno 8.4 e Node.js almeno 20. Node.js e npm sono usati per preparare gli asset; le richieste web sono gestite da PHP-FPM. PHP CLI e FPM hanno configurazioni distinte: le estensioni devono essere disponibili in entrambi.

Nel firewall del server e del provider consentire la porta SSH configurata e le porte TCP 80 e 443. Mantenere aperto l’accesso SSH quando si cambiano le regole. Non occorre esporre SQLite o la porta 8000.

#### 2. Preparare la cartella e clonare il repository

Il codice appartiene all’utente SSH; il gruppo del web server può leggerlo. Composer, npm e Git si eseguono senza `sudo`:

```bash
sudo install -d -o "$(id -un)" -g www-data -m 2750 /var/www/studiocommesse
cd /var/www/studiocommesse
umask 0027
```

Se il repository è privato e l’utente SSH non dispone già di una chiave autorizzata su GitHub, crearla **sul server**. Se `~/.ssh/id_ed25519` esiste già, usare la chiave esistente e non sovrascriverla:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519 -C "StudioCommesse server"
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519
cat ~/.ssh/id_ed25519.pub
```

Copiare soltanto la chiave pubblica `.pub` in **Settings → Deploy keys → Add deploy key** del repository GitHub, lasciando disabilitato l’accesso in scrittura, come descritto nella [guida GitHub alle chiavi di deploy](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/managing-deploy-keys). Non copiare o condividere la chiave privata. Se è stata impostata una passphrase, caricare la chiave con `ssh-add` anche nelle nuove sessioni che devono usare Git.

Al primo collegamento verificare l’impronta dell’host nelle [istruzioni GitHub](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/githubs-ssh-key-fingerprints). Per provare l’autenticazione usare `ssh -T git@github.com`: GitHub conferma l’accesso ma non offre una shell, quindi il messaggio di autenticazione riuscita con codice di uscita 1 è normale ([verifica della connessione SSH](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/testing-your-ssh-connection)).

Clonare quindi il progetto:

```bash
git clone git@github.com:archistico/StudioCommesse.git .
git log -1 --oneline
```

La cartella di destinazione deve essere vuota. La chiave per accedere al server e quella per clonare da GitHub possono essere diverse. Come alternativa usare `git clone https://github.com/archistico/StudioCommesse.git .` e l’autenticazione prevista da GitHub, senza inserire token nell’URL.

Il clone usa il ramo predefinito del repository. Per una distribuzione controllata selezionare una release o un commit già verificato prima di proseguire.

#### 3. Configurare `.env.local`

Il file `.env` versionato contiene i valori generali. Le impostazioni del singolo server vanno in `.env.local`, escluso da Git. Su una nuova installazione:

```bash
test -f .env.local || cp .env.local.dist .env.local
chmod 640 .env.local
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
nano .env.local
```

Copiare il segreto casuale appena generato in `APP_SECRET`. Il contenuto finale deve essere equivalente a questo, con un segreto reale al posto del testo di esempio:

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=INCOLLARE_QUI_IL_SEGRETO_CASUALE_GENERATO
APP_TIMEZONE=Europe/Rome
DATABASE_URL="sqlite:///%kernel.project_dir%/var/studio_commesse.db"
```

| Variabile | Configurazione |
| --- | --- |
| `APP_ENV` | `prod`, anche per il sito servito da PHP-FPM. |
| `APP_DEBUG` | `0`, per non esporre informazioni di debug. |
| `APP_SECRET` | Segreto casuale stabile e specifico dell’installazione. Conservarlo durante gli aggiornamenti. |
| `APP_TIMEZONE` | Fuso orario usato dall’applicazione; normalmente `Europe/Rome`. |
| `DATABASE_URL` | Database SQLite locale. Il valore mostrato salva il file in `var/studio_commesse.db`. |

Su un server già configurato, correggere il file esistente senza sovrascrivere credenziali o cambiare involontariamente il percorso del database. Per un percorso assoluto SQLite la forma è, ad esempio, `sqlite:////var/lib/studiocommesse/database.sqlite`: anche la cartella che contiene il file deve essere scrivibile dal processo PHP. La guida usa il percorso sotto `var/`, quindi i permessi del punto 5 sono sufficienti.

Non caricare `.env.local` nel repository o nella cartella `public`. Eventuali variabili già impostate dal sistema, da Apache o dal pool FPM possono prevalere sui file `.env`; allinearle a `prod` e debug disattivato. Anche un eventuale `.env.prod.local` o `.env.local.php` preesistente va controllato quando si modifica la configurazione. Questa procedura non richiede di generare `.env.local.php`.

#### 4. Installare le dipendenze e preparare gli asset

Restando nella radice del progetto:

```bash
export APP_ENV=prod
export APP_DEBUG=0
php scripts/php-runtime-contract.php
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts
composer check-platform-reqs --no-dev
npm ci --include=dev
npm run build
npm test
```

`composer.lock` e `package-lock.json` devono essere presenti: usarli per installare le versioni previste dalla release. Se mancano, interrompere la distribuzione e recuperare una versione completa del repository. Il build degli asset richiede le dipendenze npm di sviluppo anche quando Symfony viene eseguito in produzione. `--no-scripts` evita di avviare Symfony prima di aver preparato i permessi; la cache viene creata esplicitamente al punto 6.

#### 5. Preparare directory e permessi

SQLite deve poter creare anche i propri file temporanei nella cartella del database. L’applicazione deve inoltre scrivere cache, log, lock, documenti e backup:

```bash
mkdir -p var/cache var/log var/locks var/storage/attachments backups
sudo chgrp -R www-data /var/www/studiocommesse
sudo chmod -R g+rX,g-w,o-rwx /var/www/studiocommesse
chmod 640 .env.local
sudo setfacl -R -m u:www-data:rwX -m u:"$(id -un)":rwX var backups
sudo setfacl -dR -m u:www-data:rwX -m u:"$(id -un)":rwX var backups
sudo -u www-data test -r .env.local
sudo -u www-data test -w var
sudo -u www-data test -w var/storage/attachments
```

I comandi `test` non stampano nulla quando riescono. Le ACL valgono anche per i nuovi file e permettono a utente SSH e utente web di condividere cache e log. Il codice deve restare leggibile, ma non modificabile, dal web server. Non usare `chmod 777` e non assegnare tutto il repository a `www-data`. Per SQLite usare un disco locale, evitando cartelle condivise di rete.

Da questo punto eseguire i comandi applicativi `bin/console` come `www-data`, come mostrato negli esempi. Documenti, backup e marker di manutenzione vengono creati dall’applicazione con permessi riservati: eseguirli come utente SSH può rendere questi file illeggibili a FPM o impedire al backup di leggere gli allegati. Git, Composer e npm continuano invece a essere eseguiti dall’utente SSH.

#### 6. Creare lo schema, il primo Socio e la cache

```bash
sudo -u www-data php bin/console doctrine:migrations:migrate --env=prod --no-debug --no-interaction
sudo -u www-data php bin/console doctrine:migrations:up-to-date --env=prod --no-debug --no-interaction
sudo -u www-data php bin/console doctrine:schema:validate --env=prod --no-debug
sudo -u www-data php bin/console app:user:create --role=partner --skip-if-active-partner-exists --env=prod --no-debug
sudo -u www-data php bin/console cache:clear --env=prod --no-debug
sudo -u www-data php bin/console cache:warmup --env=prod --no-debug
```

SQLite non richiede un servizio database separato: la prima migrazione crea il file nel percorso configurato. Il comando utenti chiede nome utente, nome visualizzato e password nascosta di almeno 12 caratteri; se esiste già un Socio attivo, non crea un duplicato. Non caricare dati dimostrativi in produzione.

#### 7. Configurare PHP-FPM e Apache

Sul server dedicato dell’esempio, creare una configurazione FPM che consenta i documenti fino a 10 MiB:

```bash
sudo tee /etc/php/8.4/fpm/conf.d/99-studiocommesse.ini > /dev/null <<'PHPINI'
date.timezone = Europe/Rome
display_errors = Off
log_errors = On
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 256M
opcache.enable = 1
PHPINI
sudo a2enmod proxy_fcgi setenvif rewrite ssl
sudo a2enconf php8.4-fpm
sudo php-fpm8.4 -t
sudo systemctl enable --now php8.4-fpm apache2
sudo systemctl restart php8.4-fpm
```

Creare il VirtualHost, sostituendo il dominio nel blocco seguente:

```bash
sudo tee /etc/apache2/sites-available/studiocommesse.conf > /dev/null <<'APACHE'
<VirtualHost *:80>
    ServerName commesse.example.com
    DocumentRoot /var/www/studiocommesse/public
    DirectoryIndex index.php

    <Directory /var/www/studiocommesse/public>
        Options -Indexes +SymLinksIfOwnerMatch
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/studiocommesse-error.log
    CustomLog ${APACHE_LOG_DIR}/studiocommesse-access.log combined
</VirtualHost>
APACHE
sudo a2ensite studiocommesse.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

La configurazione `php8.4-fpm` di Debian collega Apache al socket FPM; se si usa un pool personalizzato, allineare il socket in `/etc/apache2/conf-available/php8.4-fpm.conf` a quello del pool. Su un server che usa già `mod_php`, scegliere un solo gestore PHP per il sito e adattare la configurazione prima del riavvio.

`AllowOverride All` abilita il file `public/.htaccess` già incluso nel repository. La `DocumentRoot` deve essere **solo `public/`**: database, `.env.local`, Git, backup e documenti protetti rimangono fuori dalla cartella pubblicata. Per configurazioni Apache ulteriori vedere [docs/APACHE.md](docs/APACHE.md).

#### 8. Attivare HTTPS

Prima verificare che il dominio risolva sull’indirizzo corretto, che eventuali record IPv6 siano corretti e che il sito risponda sulla porta 80. Per un dominio pubblico raggiungibile, richiedere il certificato e il reindirizzamento automatico:

```bash
sudo certbot --apache -d commesse.example.com --redirect --agree-tos --email admin@example.com
sudo systemctl enable --now certbot.timer
sudo certbot renew --dry-run
```

Aprire quindi `https://commesse.example.com/login`. Per domini interni o server non raggiungibili pubblicamente usare un certificato aziendale oppure la verifica DNS del proprio fornitore: la procedura HTTP di questo esempio richiede l’accesso pubblico sulla porta 80. Se HTTPS termina su un reverse proxy, configurare i proxy fidati di Symfony per riconoscere correttamente le richieste sicure.

#### 9. Controllare la messa in produzione

```bash
cd /var/www/studiocommesse
sudo -u www-data php bin/console about --env=prod --no-debug
sudo -u www-data php bin/console doctrine:schema:validate --env=prod --no-debug
sudo systemctl is-active apache2 php8.4-fpm
curl -I https://commesse.example.com/login
```

Con il browser verificare login del Socio, creazione di cliente e commessa, registrazione ore, caricamento e download di un documento. Per la verifica dell’accesso Collaboratore, creare un account da **Impostazioni → Gestisci utenti** e controllare che non acceda alle funzioni riservate ai Soci.

Il server PHP integrato (`php -S`) e `scripts/start-server.ps1` servono allo sviluppo locale: in produzione Apache e PHP-FPM rimangono attivi tramite systemd anche dopo la chiusura della sessione SSH.

#### 10. Creare e conservare i backup

Da Linux usare direttamente i comandi Symfony, senza PowerShell:

```bash
cd /var/www/studiocommesse
backup_dir="/var/www/studiocommesse/backups/manuale-$(date +%Y%m%d-%H%M%S)"
sudo -u www-data php bin/console app:backup:create "$backup_dir" --env=prod --no-debug
sudo -u www-data php bin/console app:backup:verify "$backup_dir" --env=prod --no-debug
```

La destinazione deve essere una cartella nuova: il comando la crea e salva database, allegati e manifest, verificandoli. Il backup coordinato può essere eseguito con il sito attivo. Pianificare l’esecuzione come `www-data` con cron o un timer systemd, usando la radice del progetto come directory di lavoro, controllarne l’esito e definire la conservazione delle copie. Conservare separatamente e in modo protetto anche `.env.local` e il commit del codice: non fanno parte del backup dei dati.

Per preparare una copia scaricabile dall’utente SSH:

```bash
sudo tar -C "$(dirname "$backup_dir")" -czf "${backup_dir}.tar.gz" "$(basename "$backup_dir")"
sudo chown "$(id -un):$(id -gn)" "${backup_dir}.tar.gz"
chmod 600 "${backup_dir}.tar.gz"
printf '%s\n' "${backup_dir}.tar.gz"
```

Da un **nuovo terminale sul proprio computer**, scaricare il percorso stampato dal server, sostituendo data e ora:

```bash
scp deploy@IP_DEL_SERVER:/var/www/studiocommesse/backups/manuale-AAAAMMGG-HHMMSS.tar.gz .
```

Proteggere la copia scaricata: contiene il database con account e dati personali, oltre agli allegati. Una copia sullo stesso disco del server non copre la perdita del server.

Il download da **Impostazioni → Scarica database** contiene solo SQLite e non sostituisce il backup completo con gli allegati. Per dettagli su integrità e ripristino vedere [docs/BACKUP_RESTORE.md](docs/BACKUP_RESTORE.md).

#### Risoluzione dei problemi più comuni

| Problema | Controllo |
| --- | --- |
| `could not find driver` | Installare/abilitare `php8.4-sqlite3` per CLI e FPM, quindi riavviare FPM. |
| `attempt to write a readonly database` | Verificare permessi e ACL del file SQLite e della sua cartella, inclusi i file `-wal`, `-shm` o journal presenti. |
| Accesso negato a cache, log, lock o allegati | Ricontrollare l’utente del pool FPM e le ACL del punto 5. |
| Home funzionante, altre pagine 404 | Verificare `DocumentRoot`, `mod_rewrite`, `AllowOverride All` e `public/.htaccess`. |
| Risposta 503 | Controllare FPM e l’eventuale manutenzione applicativa; non rimuovere il blocco durante un ripristino non verificato. |
| Documenti rifiutati per dimensione | Verificare i limiti FPM `upload_max_filesize` e `post_max_size`, poi riavviare FPM. |
| Sito senza stile | Rieseguire `npm ci --include=dev` e `npm run build`; verificare la lettura di `public/vendor`. |
| Ambiente o database inattesi | Controllare `.env.local`, eventuali altri override e variabili del processo FPM; rigenerare la cache `prod`. |

Per errori del servizio o delle richieste:

```bash
sudo journalctl -u php8.4-fpm -u apache2 -n 100 --no-pager
sudo tail -n 100 /var/log/apache2/studiocommesse-error.log
tail -n 100 var/log/operations.log
```

In produzione il logger generale usa `stderr`, raccolto dal servizio PHP/FPM secondo la sua configurazione; `operations.log` e `security-audit.log` restano sotto `var/log`. Non pubblicare i log o i segreti per diagnosticare problemi.

#### Installazione Linux per sviluppo

Per un ambiente di sviluppo con PHP, Composer, Node.js e npm già presenti è disponibile anche:

```bash
bash scripts/setup.sh
```

Questo script prepara dipendenze, asset, migrazioni e primo Socio. La procedura di produzione sopra imposta esplicitamente ambiente, permessi, servizi e HTTPS, oltre a installare Composer senza dipendenze PHP di sviluppo.

## Aggiornamento

### Windows

È preferibile estrarre la nuova release in una cartella separata e avviare da lì:

```powershell
.\scripts\update.ps1 -TargetDirectory "C:\Percorso\StudioCommesse" -Confirm UPDATE
```

Sostituire `C:\Percorso\StudioCommesse` con il percorso assoluto reale dell’installazione esistente; non usare letteralmente il segnaposto.

Se viene avviato direttamente dall’installazione da aggiornare, lo script crea automaticamente uno staging temporaneo. Crea e verifica backup di dati e codice, attiva la manutenzione, aggiorna l’applicazione e tenta il rollback automatico in caso di errore. La procedura completa è descritta in `docs/INSTALL_UPDATE.md`.

### Linux via SSH

Questa procedura manuale segue il ramo già clonato su un server dedicato e prevede una breve interruzione. `update.ps1` non viene eseguito da Bash; qui **non c’è rollback automatico**. Se si distribuiscono tag o commit specifici, sostituire `git pull --ff-only` con il checkout della release già verificata.

Entrare nella cartella, verificare che il codice sia pulito e salvare l’identificativo della versione corrente. Se `git status --short` mostra modifiche, risolverle prima di continuare; `.env.local`, `var` e `backups` sono esclusi da Git:

```bash
cd /var/www/studiocommesse
export APP_ENV=prod
export APP_DEBUG=0
git status --short
previous_commit="$(git rev-parse HEAD)"
backup_dir="/var/www/studiocommesse/backups/pre-aggiornamento-$(date +%Y%m%d-%H%M%S)"
sudo -u www-data php bin/console app:maintenance:enable --confirm=MAINTENANCE --env=prod --no-debug
sudo systemctl stop php8.4-fpm
sudo -u www-data php bin/console app:backup:create "$backup_dir" --env=prod --no-debug
sudo -u www-data php bin/console app:backup:verify "$backup_dir" --env=prod --no-debug
printf '%s\n' "$previous_commit" > "${backup_dir}.commit.txt"
install -m 600 .env.local "${backup_dir}.env.local"
```

I file del commit e della configurazione sono esterni alla cartella del backup: la verifica del manifest rifiuta file aggiuntivi al suo interno. Fermare FPM impedisce nuove scritture e lascia il sito indisponibile durante sostituzione del codice e migrazioni.

Solo dopo che il backup è riuscito e verificato:

```bash
git pull --ff-only
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts
composer check-platform-reqs --no-dev
npm ci --include=dev
npm run build
npm test
sudo -u www-data php bin/console doctrine:migrations:migrate --env=prod --no-debug --no-interaction
sudo -u www-data php bin/console doctrine:migrations:up-to-date --env=prod --no-debug --no-interaction
sudo -u www-data php bin/console doctrine:schema:validate --env=prod --no-debug
sudo -u www-data php bin/console cache:clear --env=prod --no-debug
sudo -u www-data php bin/console cache:warmup --env=prod --no-debug
sudo -u www-data php bin/console app:maintenance:disable --confirm=CLEAR --env=prod --no-debug
sudo systemctl start php8.4-fpm
curl -I https://commesse.example.com/login
```

Le ACL configurate durante l’installazione continuano ad applicarsi ai nuovi file operativi. Ricontrollare accesso, documenti e timer dal browser. Non sostituire o cancellare `.env.local`, `var`, `backups` e il database durante l’aggiornamento.

**Se un comando fallisce, fermarsi:** non disattivare la manutenzione e non avviare FPM finché codice, schema e dati non sono coerenti. Per tornare alla versione precedente, usare il commit salvato e il backup verificato dello stesso aggiornamento. Recuperare il loro valore dai propri file se la sessione SSH è stata chiusa; i comandi seguenti assumono che `previous_commit` e `backup_dir` siano ancora valorizzati correttamente:

```bash
git checkout --detach "$previous_commit"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts
npm ci --include=dev
npm run build
sudo -u www-data php bin/console app:backup:restore "$backup_dir" \
  --safety-backup-dir="/var/www/studiocommesse/backups/prima-del-rollback-$(date +%Y%m%d-%H%M%S)" \
  --confirm=RESTORE --env=prod --no-debug
sudo -u www-data php bin/console doctrine:schema:validate --env=prod --no-debug
sudo -u www-data php bin/console cache:clear --env=prod --no-debug
sudo -u www-data php bin/console cache:warmup --env=prod --no-debug
sudo -u www-data php bin/console app:maintenance:disable --confirm=CLEAR --env=prod --no-debug
sudo systemctl start php8.4-fpm
```

Il ripristino sostituisce database e allegati con lo snapshot precedente e crea una copia di sicurezza dello stato corrente. Se fallisce, lasciare il servizio fermo e verificare l’errore. Dopo `checkout --detach`, il successivo aggiornamento dovrà selezionare esplicitamente il ramo o la release desiderata.

## Apache

Il pacchetto include `symfony/apache-pack` e `public/.htaccess`. La configurazione del VirtualHost è descritta in `docs/APACHE.md`.

## Sicurezza e manuali

Il login è protetto da CSRF e rate limiting: dopo 5 tentativi falliti dalla stessa utenza e postazione, nuovi tentativi vengono sospesi per un’ora. Per dati personali e produzione usare HTTPS. I manuali utente, amministratore, sicurezza e accessibilità sono disponibili in `docs/`.

## Avvio locale

```powershell
.\scripts\start-server.ps1
```

L’applicazione è disponibile, salvo diversa configurazione, su `http://localhost:8000`.

## Script principali

| Script | Scopo |
| --- | --- |
| `setup.ps1` | Esegue l’installazione iniziale o riallinea un ambiente già configurato. |
| `setup.sh` | Prepara un ambiente Linux di sviluppo; per la produzione seguire la guida SSH sopra. |
| `update.ps1` | Aggiorna un’installazione esistente con backup, manutenzione e rollback. |
| `release-preflight.ps1` | Verifica sorgente, requisiti e pulizia del pacchetto. |
| `validate.ps1` | Esegue controlli statici, test e smoke test del pacchetto. |
| `benchmark-capacity.ps1` | Misura i profili isolati da 30, 200 e 600 commesse. |
| `start-server.ps1` | Avvia il server PHP locale. |
| `load-fixtures.ps1` | Carica dati dimostrativi; non viene eseguito dal setup ordinario. |
| `backup.ps1` | Crea e verifica un backup di database e allegati. |
| `verify-backup.ps1` | Controlla l’integrità di un backup esistente. |
| `restore-backup.ps1` | Ripristina un backup dopo conferma esplicita. |
| `clear-database-keep-users.ps1` | Svuota i dati operativi conservando utenti e migrazioni. |
| `package-release.ps1` | Genera uno ZIP pulito, senza dati locali o dipendenze installate. |

## Validazione

```powershell
.\scripts\validate.ps1
```

La documentazione tecnica e operativa dettagliata è disponibile nella cartella `docs`.

## Riferimenti per Linux

- [Pacchetti Debian 13: PHP-FPM](https://packages.debian.org/trixie/php8.4-fpm), [Node.js](https://packages.debian.org/trixie/nodejs) e [Certbot per Apache](https://packages.debian.org/trixie/python3-certbot-apache).
- [Deploy Symfony](https://symfony.com/doc/current/deployment.html), [permessi con ACL](https://symfony.com/doc/current/setup/file_permissions.html) e [configurazione del web server](https://symfony.com/doc/current/setup/web_server_configuration.html).
- [Guida ufficiale Certbot](https://certbot.eff.org/instructions) per certificati e varianti della procedura HTTPS.
