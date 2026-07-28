# Report mensile operativo

L’area Soci `/report/mensile` riunisce lavoro, costi e movimenti del mese selezionato. Tutte le sezioni rispettano il filtro opzionale per commessa.

## Contenuto

- ore totali e fatturabili, registrazioni concluse e persone coinvolte;
- andamento delle commesse con attività, avanzamento, residuo, costi e incassi;
- riepilogo aggregato per utente;
- dettaglio cronologico delle registrazioni ore;
- conteggio e cronologia delle azioni audit;
- CSV separati per dettaglio ore e riepilogo utenti.

## Riepilogo ore e costi per utente

La tabella include ogni utente che possiede almeno una registrazione conclusa nel periodo, anche quando l’account è stato successivamente disattivato.

- **Ore registrate**: somma delle durate concluse; i timer aperti sono esclusi.
- **Tariffa standard attuale**: valore corrente dell’anagrafica utente.
- **Costo standard teorico**: totale minuti conclusi × tariffa standard attuale, arrotondato ai centesimi sul totale del periodo.
- **Costo storico effettivo**: somma dei costi congelati sulle singole registrazioni, quindi conserva override di attività o commessa e variazioni tariffarie avvenute nel tempo.
- **Scostamento**: costo storico effettivo meno costo standard teorico.

Quando la tariffa standard è zero, la pagina mostra **Non impostata** e non calcola costo teorico o scostamento. Il costo storico resta comunque disponibile se le registrazioni hanno uno snapshot tariffario valido.

## Regole temporali

Le registrazioni appartengono al mese della data di inizio. I timer aperti restano visibili nel dettaglio, ma non contribuiscono a ore e costi consolidati. Lo stato, l’avanzamento e il residuo delle attività sono quelli correnti.

## Permessi

Il report e le due esportazioni CSV sono riservati ai Soci perché contengono costi, tariffe e audit globale.
