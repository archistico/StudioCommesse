# Checklist M9.2-H

## Gate automatico

0. Il preflight PHP conferma runtime ed estensioni obbligatorie.
1. Tutti gli script PowerShell superano il parser.
2. Composer/npm, audit dipendenze e build asset passano.
3. Lint PHP, YAML e Twig passano.
4. Tutti i contratti da M8 a M9.2-H passano.
5. Migrazioni, schema Doctrine e backup smoke passano.
6. Il pacchetto smoke viene creato, confrontato ed estratto in una cartella pulita.
7. PHPStan livello 8 non segnala errori.
8. PHPUnit esegue **244 test** senza errori o failure.
9. I benchmark isolati 30/200/600 rispettano i budget.
10. `validate.ps1` termina con `M9.2-H VALIDATION PASSED`.

## Sicurezza login

1. Verificare 5 tentativi falliti e blocco del tentativo successivo per un’ora.
2. Confermare che il messaggio non distingua utente inesistente, password errata, account disattivato o throttling.
3. Verificare in Audit gli eventi Accesso non riuscito e Accesso temporaneamente bloccato.
4. Confermare che il nome utente tentato non sia salvato in chiaro nei fallimenti.
5. Controllare che password, token, cookie e session ID non compaiano in database audit o log.
6. Verificare CSP, HSTS su HTTPS, no-referrer, anti-frame, no-sniff e cache no-store.
7. Confermare `cookie_httponly`, `cookie_secure: auto` e `cookie_samesite: strict`.

## Accessibilità e responsive

1. Eseguire la checklist di `docs/ACCESSIBILITY.md`.
2. Navigare login, dashboard, commesse, attività, ore, report, audit e utenti senza mouse.
3. Verificare focus visibile, skip link e pagina corrente.
4. Provare zoom 200% e larghezze 360/768/1024/1440 pixel.
5. Verificare tabelle e azioni sui display stretti.
6. Confermare che i messaggi siano annunciati come alert o status.

## Manuali e packaging

1. Controllare `USER_MANUAL.md`, `ADMIN_MANUAL.md`, `SECURITY.md` e `ACCESSIBILITY.md`.
2. Eseguire `package-release.ps1`.
3. Verificare `dist/StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip`.
4. Eseguire `verify-release-package.ps1` e `install-smoke.ps1` sullo ZIP.
5. Confermare l’assenza di database, log, backup, cache, credenziali e dipendenze installate.
