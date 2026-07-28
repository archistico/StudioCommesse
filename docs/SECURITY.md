# Sicurezza applicativa

## Obiettivo

Studio Commesse contiene dati personali, operativi ed economici. L’accesso deve quindi avvenire soltanto da postazioni autorizzate, tramite HTTPS e con account individuali. Le password non devono essere condivise.

## Protezione del login

- Il login è protetto da token CSRF.
- Dopo 5 tentativi falliti sulla stessa combinazione di nome utente e indirizzo IP, i nuovi tentativi vengono sospesi per un’ora.
- Symfony applica anche un limite globale per indirizzo IP, utile contro tentativi eseguiti su molti nomi utente.
- Un accesso riuscito azzera il contatore relativo alla combinazione interessata.
- Il messaggio mostrato all’utente resta generico e non conferma se il nome utente esiste, è disattivato o è temporaneamente bloccato.
- Dopo un errore il form non conserva né ristampa il nome utente digitato.

Il blocco non modifica l’anagrafica dell’utente e scade automaticamente. Non è necessario intervenire sul database.

## Audit degli accessi

Il registro Audit, riservato ai Soci, distingue:

- accesso riuscito;
- accesso non riuscito;
- accesso temporaneamente bloccato.

Per i tentativi falliti il nome utente digitato non viene salvato in chiaro. Viene registrata un’impronta non reversibile che consente di correlare tentativi ripetuti senza esporre l’identificativo. Password, token CSRF, cookie, session ID e contenuto delle richieste non vengono registrati.

I file `security-audit.log` usano inoltre impronte per attore e indirizzo IP e riportano solo i nomi dei campi di dettaglio, non i loro valori. Il database audit conserva le informazioni operative necessarie ed è accessibile soltanto ai Soci.

## Intestazioni HTTP

Le risposte dinamiche includono:

- divieto di incorporamento in frame;
- blocco del MIME sniffing;
- politica `no-referrer`;
- Content Security Policy;
- disabilitazione di fotocamera, microfono e geolocalizzazione;
- isolamento della finestra principale;
- cache privata `no-store`;
- HSTS quando la richiesta usa HTTPS.

## Configurazione Apache

In produzione:

1. usare HTTPS con un certificato valido;
2. puntare il `DocumentRoot` esclusivamente a `public`;
3. non rendere accessibili `var`, `.env.local`, `backups`, `config` e `vendor` come directory web;
4. limitare l’accesso alla rete interna o a una VPN;
5. proteggere i file di log e backup con permessi del filesystem;
6. mantenere PHP, Composer e dipendenze aggiornati dopo validazione.

La configurazione di base è descritta in `docs/APACHE.md`.

## Risposta a un incidente

In caso di tentativi anomali:

1. consultare Audit filtrando il gruppo Sicurezza;
2. cercare picchi di accessi falliti e blocchi temporanei;
3. verificare gli indirizzi IP e i request ID interessati;
4. disattivare l’account se si sospetta una compromissione;
5. cambiare la password da una postazione affidabile;
6. conservare una copia protetta dei log e del backup necessario all’analisi;
7. non inviare log o backup tramite canali non cifrati.

## Limiti

Il rate limiting applicativo protegge il login ma non sostituisce firewall, restrizioni di rete, aggiornamenti del sistema operativo, HTTPS e monitoraggio del server Apache.
