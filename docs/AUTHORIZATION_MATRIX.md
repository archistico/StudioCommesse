# Matrice autorizzazioni e riservatezza

Questa matrice descrive la politica server-side delle 64 rotte applicative. La versione verificabile è `config/authorization_matrix.php`; una nuova rotta deve essere aggiunta alla matrice prima che il gate possa passare.

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
| Eliminare una registrazione ore o un timer di qualunque utente | Sì | No |
| Eliminare un'attività e tutte le ore collegate | Sì | No |
| Avviare o fermare il proprio timer | Sì | Sì |
| Annullare il proprio timer con conferma esplicita | Sì | Sì |

Le registrazioni associate a commesse archiviate sono consultabili ma non modificabili.

L'annullamento usa app_timer_stop con intent=cancel, un token CSRF dedicato all'identificativo della registrazione e una verifica server-side del timer attivo dell'utente. Una conferma rimasta aperta non può annullare una registrazione diversa o già conclusa. L'operazione e il relativo audit sono atomici.

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

I percorsi di scelta app_activity_choose_project, app_attachment_choose_project e app_time_entry_choose_activity richiedono un account attivo e mostrano soltanto commesse non archiviate. La registrazione ore verifica anche che l'attività appartenga alla commessa scelta. Queste rotte GET non salvano dati: indirizzano ai form esistenti, che mantengono i controlli di autorizzazione e CSRF. app_attachment_new separa il caricamento dall'archivio della commessa e nega anche il GET su commesse archiviate; il precedente POST sull'archivio resta compatibile.

Anche app_expense_choose_project e app_payment_choose_project selezionano soltanto commesse attive. Il primo mantiene l'accesso collaboratore per le spese proprie; il secondo richiede ROLE_PARTNER prima di caricare l'elenco. Entrambi indirizzano ai form economici esistenti.

## Amministrazione

Le rotte `app_settings_index` (GET), `app_settings_backup` (POST), `app_settings_reset` (POST) e `app_user_delete` (GET/POST) richiedono `ROLE_PARTNER`. Backup e azzeramento richiedono CSRF; l’azzeramento richiede anche `CANCELLA DATI` e un lock esclusivo delle richieste. Lo svuotamento globale include esplicitamente i dati archiviati, conservando utenti e migrazioni. L’eliminazione di un singolo utente richiede conferma e CSRF, nessun dato applicativo collegato e protegge account in uso e ultimo socio attivo. Il registro audit storico non blocca l’eliminazione dell’utente.

Tutte le rotte `/admin/utenti`, `/controllo`, `/report/mensile`, `/report/collaboratori` e `/audit`, incluse le esportazioni CSV, richiedono `ROLE_PARTNER` lato server. Il registro audit contiene dati di sicurezza e correlazione tecnica e non viene mai esposto ai Collaboratori.

Il Report collaboratori comprende panoramica, dettaglio personale, CSV riepilogo e CSV registrazioni. Sono tutte consultazioni GET riservate ai Soci; lo storico include anche le commesse archiviate e non abilita scritture. Le ore sono attribuite all’autore della registrazione, le attività all’assegnatario attuale. Il dettaglio economico usa i costi storici consolidati.

## Controlli del gate

M9.2-B verifica:

1. corrispondenza esatta tra rotte nominate e matrice;
2. presenza di metodi, accesso, proprietà, politica archivio e classificazione dati;
3. protezione delle aree riservate ai Soci;
4. blocco delle scritture su commesse archiviate;
5. impossibilità di modificare risorse altrui;
6. assenza di dati economici nel markup consegnato ai Collaboratori;
7. rifiuto dei campi amministrativi/finanziari aggiunti manualmente ai POST.

## Eliminazioni di attività e ore

Le rotte app_activity_delete e app_time_entry_delete richiedono ROLE_PARTNER per GET e POST. Il GET mostra soltanto il riepilogo; il POST verifica il CSRF specifico del record prima di cancellare. I soci possono intervenire sui dati di qualsiasi utente e anche sui timer attivi; le commesse archiviate restano in sola lettura. L'eliminazione dell'attività include tutte le sue registrazioni ore, mentre spese e documenti vengono conservati nella commessa senza il collegamento all'attività. Eliminazione e audit sono atomici e condividono il lock delle mutazioni dei timer.
