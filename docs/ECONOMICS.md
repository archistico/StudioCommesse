# Economia della commessa

M5 fornisce controllo gestionale, non contabilità fiscale.

## Importi

Gli importi sono memorizzati come centesimi interi e mostrati in euro con formato italiano. I form accettano direttamente valori in euro.

## Costo delle ore

Il costo di una registrazione conclusa è:

`durata in minuti × tariffa oraria / 60`

La tariffa viene congelata al salvataggio manuale oppure all’avvio del timer; il costo viene calcolato quando la registrazione è conclusa e resta storico.

## Riepilogo

Per ogni commessa sono mostrati:

- preventivo;
- costo ore;
- spese;
- costo totale;
- incassato;
- residuo da incassare;
- margine gestionale.

Il margine non è un risultato fiscale e non considera imposte, IVA, ammortamenti o costi generali non registrati.


## Chiusura economica M6.2

La chiusura economica è un indicatore derivato:

- `Preventivo mancante` quando il preventivo è zero;
- `Da incassare` quando non risultano incassi;
- `Parzialmente incassata` quando l’incassato è inferiore al preventivo;
- `Incassata` quando l’incassato raggiunge o supera il preventivo;
- `Non applicabile` per una commessa annullata.

Il saldo gestionale mensile è `incassi − costo storico ore − spese`. È un indicatore interno e non un risultato fiscale o una prima nota.
