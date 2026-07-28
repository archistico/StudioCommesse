# Audit operativo e logging

L’area `/audit` è riservata ai Soci e raccoglie gli eventi applicativi e di sicurezza già persistiti nel database. I Collaboratori non possono aprire né esportare il registro.

## Consultazione

Il registro consente di filtrare per gruppo, azione, attore, request ID e intervallo di date. I risultati sono paginati lato server e possono essere esportati in CSV UTF-8 mantenendo gli stessi filtri.

Ogni riga mostra data e ora, azione, attore, soggetto, IP, dettagli funzionali e metadati di correlazione. Il request ID permette di collegare un evento audit alla risposta HTTP e ai log tecnici.

## Correlazione

Quando una richiesta produce un evento audit, `AuditLogger` aggiunge automaticamente, se disponibili:

- `request_id`;
- rotta Symfony;
- metodo HTTP;
- indirizzo IP, quando non è già stato fornito dal chiamante.

I metadati tecnici restano separati dai dettagli funzionali nell’interfaccia e nell’esportazione.

## Log su file

- `var/log/security-audit.log`: eventi audit applicativi e di sicurezza in JSON;
- `var/log/operations.log`: errori HTTP gestiti e anomalie operative in JSON;
- log ordinario Symfony: errori non gestiti e diagnostica generale.

I file di log non fanno parte del pacchetto di distribuzione e non devono essere pubblicati o allegati senza verifica dei dati contenuti.

## Sicurezza del login

Gli eventi di autenticazione distinguono accessi riusciti, fallimenti e blocchi temporanei. Dopo cinque fallimenti sulla stessa combinazione di utenza e indirizzo IP, i tentativi sono sospesi per un’ora; il limite globale per IP resta attivo.

Nei fallimenti il nome utente digitato non viene conservato in chiaro: l’audit usa un’impronta non reversibile per correlare i tentativi. Password, token, cookie e session ID non vengono mai registrati. I log JSON su file contengono soltanto impronte e nomi dei campi, non valori descrittivi di clienti, commesse o persone.
