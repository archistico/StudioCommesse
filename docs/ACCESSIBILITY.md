# Accessibilità e comportamento responsive

## Principi applicati

- collegamento iniziale per saltare direttamente al contenuto;
- landmark distinti per navigazione e contenuto principale;
- pagina corrente dichiarata con `aria-current`;
- intestazioni delle tabelle dichiarate con `scope="col"`;
- messaggi di errore annunciati come alert e messaggi informativi come status;
- focus da tastiera ad alto contrasto;
- controlli principali con altezza minima adatta all’uso tattile;
- riduzione delle animazioni quando richiesta dal sistema operativo;
- azioni e filtri impilati sui display piccoli;
- tabelle scorrevoli e DataTables responsive.

## Verifica manuale minima

Per login, dashboard, commesse, attività, ore, report mensile, audit e utenti:

1. navigare senza mouse;
2. verificare che il focus sia sempre visibile;
3. controllare che il collegamento di salto porti al contenuto;
4. provare zoom browser al 200%;
5. provare larghezze 360, 768, 1024 e 1440 pixel;
6. verificare che nessun comando sia disponibile soltanto tramite colore o icona;
7. controllare la lettura dei messaggi di errore con uno screen reader;
8. verificare le tabelle sia in modalità normale sia responsive.

## Limiti noti

I componenti Tabler e DataTables sono inclusi localmente e vengono usati con progressive enhancement. La tabella HTML deve restare comprensibile anche prima dell’inizializzazione JavaScript.
