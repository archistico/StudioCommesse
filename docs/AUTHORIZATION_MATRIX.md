# Matrice autorizzazioni e riservatezza

Questa matrice descrive la politica server-side delle 48 rotte applicative. La versione verificabile è `config/authorization_matrix.php`; una nuova rotta deve essere aggiunta alla matrice prima che il gate possa passare.

## Livelli di accesso

- **Pubblico**: soltanto login.
- **Collaboratore**: qualsiasi account attivo autenticato; i Soci ereditano questo ruolo.
- **Socio**: `ROLE_PARTNER` obbligatorio.
- **Proprietario o Socio**: la risorsa deve appartenere all’utente oppure l’utente deve essere Socio.
- **Responsabile o Socio**: modifica della commessa tramite `ProjectVoter`.
- **Editor attività o Socio**: assegnatario, autore, responsabile della commessa o Socio.
- **Gestore documento o Socio**: uploader, responsabile, assegnatario/autore dell’attività collegata o Socio.

## Regola generale per gli archivi

Clienti e commesse archiviati restano consultabili. Nessuna operazione di scrittura è consentita sui dati collegati a una commessa archiviata: commessa, attività, ore, spese, incassi e metadati/documenti sono in sola lettura. I controlli sono applicati nei controller, nei voter o nei servizi, non soltanto nei template.

## Rotte pubbliche e sessione

| Rotta | Metodi | Accesso | Note |
|---|---|---|---|
| `app_login` | GET, POST | Pubblico | Autenticazione con CSRF e throttling |
| `app_logout` | POST | Collaboratore | Logout intercettato dal firewall e protetto da CSRF |
| `app_home` | GET | Collaboratore | Reindirizza alla dashboard |
| `app_dashboard` | GET | Collaboratore | Dati operativi |

## Clienti e commesse

| Area | Consultazione | Scrittura | Riservatezza |
|---|---|---|---|
| Clienti | Tutti gli autenticati | Solo Soci | Colonna `Dovuto` soltanto ai Soci |
| Commesse | Tutti gli autenticati | Creazione/archivio solo Soci; modifica Socio o responsabile | Nota riservata soltanto a Socio e responsabile; controllo economico soltanto ai Soci |
| Attività | Tutti gli autenticati | Creazione libera su commesse attive; modifica secondo proprietà | Override tariffa soltanto ai Soci |

Le richieste POST costruite manualmente non possono assegnare campi amministrativi o finanziari non presenti nel form del Collaboratore.

## Ore e timer

| Operazione | Socio | Collaboratore |
|---|---:|---:|
| Consultare report Ore e dettaglio attività | Sì | Sì |
| Vedere tariffa e costo storico | Sì | No |
| Registrare ore proprie | Sì | Sì |
| Modificare ore proprie | Sì | Sì |
| Modificare ore altrui | Sì | No |
| Avviare o fermare il proprio timer | Sì | Sì |

Le registrazioni associate a commesse archiviate sono consultabili ma non modificabili.

## Economia

| Operazione | Socio | Collaboratore |
|---|---:|---:|
| Vedere quadro economico completo | Sì | No |
| Vedere tutte le spese | Sì | No |
| Vedere le proprie spese | Sì | Sì |
| Creare spese su commesse attive | Sì | Sì |
| Modificare/eliminare spese proprie | Sì | Sì |
| Modificare/eliminare spese altrui | Sì | No |
| Consultare o gestire incassi | Sì | No |
| Controllo, dovuti e report mensile | Sì | No |

La responsabilità della commessa non attribuisce privilegi economici aggiuntivi a un Collaboratore.

## Documenti

Tutti gli autenticati possono consultare e scaricare documenti. La modifica o eliminazione richiede il voter documentale. Su commesse archiviate il download resta consentito, mentre upload, metadati ed eliminazione sono bloccati anche tramite URL diretto.

## Amministrazione

Tutte le rotte `/admin/utenti`, `/controllo`, `/report/mensile` e `/audit`, incluse le esportazioni CSV, richiedono `ROLE_PARTNER` lato server. Il registro audit contiene dati di sicurezza e correlazione tecnica e non viene mai esposto ai Collaboratori.

## Controlli del gate

M9.2-B verifica:

1. corrispondenza esatta tra rotte nominate e matrice;
2. presenza di metodi, accesso, proprietà, politica archivio e classificazione dati;
3. protezione delle aree riservate ai Soci;
4. blocco delle scritture su commesse archiviate;
5. impossibilità di modificare risorse altrui;
6. assenza di dati economici nel markup consegnato ai Collaboratori;
7. rifiuto dei campi amministrativi/finanziari aggiunti manualmente ai POST.
