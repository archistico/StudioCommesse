# Flussi end-to-end

La suite M9.2-E verifica i percorsi operativi principali attraversando controller, form Symfony, autorizzazioni, repository, calcoli di chiusura, audit e backup.

## Scenari coperti

1. **Commessa completa e pagata**: attività conclusa, ore consuntivate, spesa, saldo, controllo di chiusura senza criticità e archiviazione con audit.
2. **Collaboratore**: creazione libera di un’attività, assegnazione, registrazione delle proprie ore e accesso alla sola vista economica consentita.
3. **Chiusura incoerente**: una commessa dichiarata completata con attività ancora aperte viene evidenziata come stato incoerente e richiede attenzione.
4. **Archiviazione e ripristino**: una commessa non può essere ripristinata finché il cliente resta archiviato; il ripristino nell’ordine corretto riattiva entrambi.
5. **Backup e ripristino**: utenti, cliente, commessa, attività, ore, spesa, incasso, audit e allegato vengono ripristinati insieme; il backup di sicurezza conserva lo stato sostituito.

## Confini

La milestone aggiunge copertura di integrazione e contratti di rilascio. Non introduce nuove entità, tabelle, rotte o dipendenze e non modifica i permessi applicativi.


## Ciclo di vita Doctrine nei test web

Dopo una richiesta del browser di test, i servizi Doctrine possono essere resettati anche con il riavvio del kernel disabilitato. Gli scenari conservano quindi gli identificativi e ricaricano le entità dal database prima delle verifiche successive, evitando di usare `refresh()` su oggetti detached.
