# Permessi

## Matrice generale

| Operazione | Socio | Responsabile della commessa | Altro collaboratore |
|---|---:|---:|---:|
| Consultare clienti e commesse | Sì | Sì | Sì |
| Gestire clienti | Sì | No | No |
| Creare una commessa | Sì | No | No |
| Modificare dati operativi della commessa | Sì | Solo propria | No |
| Vedere nota riservata | Sì | Solo propria | No |
| Creare attività | Sì | Sì | Sì |
| Modificare attività | Sì | Propria commessa | Se assegnatario o autore |
| Registrare le proprie ore | Sì | Sì | Sì |
| Consultare il report globale delle ore | Sì | Sì | Sì |
| Consultare il controllo avanzato operativo/economico | Sì | No | No |
| Consultare la valutazione giornaliera di ogni persona | Sì | No | No |
| Correggere ore proprie | Sì | Sì | Sì |
| Correggere ore altrui | Sì | No | No |
| Consultare e scaricare documenti | Sì | Sì | Sì |
| Caricare documenti su commesse attive | Sì | Sì | Sì |
| Modificare/eliminare un documento | Sì | Se responsabile, uploader, assegnatario o creatore attività | Solo se uploader, assegnatario o creatore attività |
| Gestire utenti | Sì | No | No |

## Economia

La responsabilità della commessa non attribuisce una visibilità economica aggiuntiva. Un responsabile con ruolo Collaboratore segue le stesse regole economiche di ogni altro Collaboratore.
Il riepilogo mensile per utente, inclusi tariffa standard, costo teorico e costo storico, è riservato ai Soci.

| Operazione economica | Socio | Collaboratore, anche responsabile |
|---|---:|---:|
| Vedere preventivo, costo ore, margine e residuo | Sì | No |
| Vedere tutte le spese della commessa | Sì | No |
| Vedere le proprie spese | Sì | Sì |
| Creare una spesa su una commessa attiva | Sì | Sì |
| Modificare o eliminare una propria spesa | Sì | Sì |
| Modificare o eliminare spese altrui | Sì | No |
| Consultare o gestire incassi | Sì | No |
| Consultare importi dovuti per cliente | Sì | No |
| Consultare il report mensile economico | Sì | No |

Il report globale delle ore espone esclusivamente dati operativi. Tariffe e costi restano protetti nelle aree riservate ai soci, anche quando il Collaboratore è responsabile della commessa. L’area `Controllo`, inclusa la valutazione giornaliera delle persone, combina indicatori operativi ed economici trasversali ed è riservata ai soci.

I controlli sono applicati lato server tramite ruoli, voter, controlli nei controller e nei servizi e token CSRF. La sola visibilità Twig non costituisce autorizzazione.

I file sono serviti esclusivamente dal controller protetto. Le commesse archiviate consentono consultazione e download ma non nuovi caricamenti; l’invariante viene applicato anche da `AttachmentManager` per bloccare richieste costruite manualmente.

## Elementi archiviati

Le commesse archiviate e i dati collegati restano consultabili, ma sono in sola lettura. È vietato modificare attività e ore, creare/modificare/eliminare spese o incassi e caricare/modificare/eliminare documenti. Il download dei documenti resta consentito. La regola è applicata lato server anche per richieste costruite manualmente.
