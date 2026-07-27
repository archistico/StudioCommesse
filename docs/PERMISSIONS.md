# Permessi

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
| Vedere riepilogo economico | Sì | Solo propria commessa | No |
| Modificare preventivo e tariffe | Sì | No | No |
| Creare/modificare/eliminare spese e incassi | Sì | No | No |
| Consultare e scaricare documenti | Sì | Sì | Sì |
| Caricare documenti su commesse attive | Sì | Sì | Sì |
| Modificare/eliminare un documento | Sì | Se responsabile, uploader, assegnatario o creatore attività | Solo se uploader, assegnatario o creatore attività |
| Gestire utenti | Sì | No | No |

Il report globale delle ore espone esclusivamente dati operativi. Tariffe e costi restano protetti nel contesto della singola commessa. L’area `Controllo`, inclusa la valutazione giornaliera delle persone, combina indicatori operativi ed economici trasversali ed è riservata ai soci.

I controlli sono applicati lato server tramite ruoli, `ProjectVoter`, controlli nei controller e token CSRF. La sola visibilità Twig non costituisce autorizzazione.

I file sono serviti esclusivamente dal controller protetto. Le commesse archiviate consentono consultazione e download ma non nuovi caricamenti.
