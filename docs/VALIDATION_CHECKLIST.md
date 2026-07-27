# Checklist M7

## Gate automatico

1. `setup.ps1 -SkipPartnerBootstrap` termina senza errori.
2. `validate.ps1` termina con `M7 VALIDATION PASSED`.
3. PHPStan non riporta errori.
4. PHPUnit è completamente verde.
5. Migrazioni e mapping Doctrine sono sincronizzati.
6. I tre errori PHPStan nei controller `CollaboratorEvaluationController` e `ControlController` non ricompaiono.

## Verifica M6.1 inclusa

1. In `/commesse/{id}` le attività mostrano totale e dettaglio ore per persona.
2. `/attivita` filtra per `Assegnatario`.
3. `/ore` filtra per commessa, attività, persona, date e fatturabilità.
4. La paginazione mantiene i filtri e i totali considerano l’intero risultato.
5. Il report globale non espone tariffe o costi.

## Prova manuale M6.2

1. Un socio vede la voce `Controllo`; un collaboratore non la vede e riceve 403 su `/controllo`.
2. La tabella commesse distingue chiusura operativa, economica e complessiva.
3. Una commessa completata con tutte le attività chiuse e preventivo interamente incassato risulta `Chiusa completamente`.
4. Una commessa completata con attività o timer aperti risulta `Stato incoerente`.
5. Una commessa con lavoro chiuso ma importo residuo risulta `Lavoro chiuso, da incassare`.
6. Le commesse senza avanzamenti da oltre 14 giorni mostrano `Ferma`.
7. Una persona con più di 8 attività aperte o oltre 40:00 residue mostra `Sovraccarico`.
8. Lo scostamento ore conserva correttamente il segno positivo o negativo.
9. Costi oltre preventivo, ritardi e preventivo mancante generano criticità leggibili.
10. I riepiloghi per persona, cliente e mese rispettano cliente, responsabile e periodo.
11. Filtri e ordinamento restano selezionati tornando a `/controllo` senza parametri.
12. `Azzera` elimina i filtri persistenti.
13. Nel dettaglio commessa un socio vede `Controllo chiusura`; un collaboratore no.
14. Il saldo gestionale è descritto come indicatore non fiscale.


## Prova manuale M6.2 Hotfix 1

1. Aprire `Controllo` come socio.
2. Nella tabella `Carico per persona`, aprire `Dettaglio giornaliero`.
3. Verificare che ogni giornata mostri commessa, attività, lavoro svolto, intervallo, durata e totale del giorno.
4. Verificare che il totale complessivo coincida con le ore del periodo nella riga della persona usando gli stessi filtri.
5. Cambiare periodo, commessa e fatturabilità e verificare l’aggiornamento dei totali.
6. Accedere come collaboratore e verificare che `/controllo/collaboratori/{id}` restituisca accesso negato.

## Prova manuale M6.2 Hotfix 2

1. Aprire in creazione e modifica i form di commessa, attività, cliente, utente, registrazione ore, spesa e incasso.
2. Su schermo grande verificare che i campi compatti siano disposti in due colonne di uguale larghezza.
3. Su schermo piccolo e medio verificare che tutti gli input tornino a larghezza piena, uno per riga.
4. Verificare che descrizioni, note, indirizzo e motivi testuali occupino sempre una riga completa.
5. Verificare che `Tariffa oraria standard` sia visibile nel form utente prima del pulsante.
6. Verificare che Preventivo, tariffe e tutti gli altri campi condizionali compaiano prima del pulsante.
7. Verificare che nessun input venga aggiunto automaticamente sotto il pulsante di salvataggio.
8. Verificare che ogni pulsante principale `Crea` o `Salva` occupi l’intera larghezza del footer del form.


## Regressioni Hotfix 4

- La sidebar deve contenere link funzionanti a `/controllo` per i soci e `/ore` per tutti gli utenti autenticati.
- I test non devono richiedere un elemento HTML `<nav>` quando il layout usa una sidebar `<aside>`.
- I contratti sorgente devono passare sia con file LF sia con file CRLF.
- La costante di 2.400 minuti può usare il separatore numerico PHP senza far fallire il test.


## Prova manuale M6.3

1. Ridimensionare `/commesse/{id}`, `/clienti/{id}`, `/controllo`, `/ore` e la dashboard sotto 992 px e verificare che ogni struttura multi-colonna diventi monocolonna.
2. Aprire `/attivita`, lasciare selezionato `Le mie attività`, premere `Mostra` e verificare che non si produca alcun errore di conversione.
3. Verificare che la spiegazione del filtro attività occupi una riga autonoma e rimanga leggibile.
4. In `/clienti` aprire un cliente cliccando sul nome e verificare l’assenza delle colonne `Apri` e `Azioni`.
5. Verificare ordinamento e ricerca DataTables in ogni tabella con dati.
6. Su schermo stretto verificare che Responsive renda consultabili le colonne senza sovrapposizioni.
7. Nel report `/ore` verificare che DataTables cerchi nella pagina corrente senza introdurre una seconda paginazione browser.
8. Verificare che codice commessa, nome cliente, titolo attività, nome utente e descrizione economica siano i collegamenti principali nelle rispettive liste.
9. Verificare che archiviazione di commesse/clienti ed eliminazione di spese/incassi siano disponibili nelle relative pagine di modifica e non nelle tabelle.
10. Disconnettere la rete dopo il setup e verificare che stile e funzioni DataTables restino disponibili, perché gli asset sono locali.

## Regressioni M6.3 Hotfix 1

1. Aprire la modifica di un cliente attivo come socio e verificare che `Archivia cliente` sia presente.
2. Verificare che un cliente con commesse non archiviate non venga archiviato.
3. Dopo aver archiviato tutte le commesse, verificare che il cliente possa essere archiviato.
4. Aprire la modifica di una commessa attiva come socio e verificare che `Archivia commessa` sia presente.
5. Verificare che una commessa aperta non venga archiviata e che una commessa completata possa esserlo.
6. In `/attivita?assignee=me`, verificare che il form mantenga `Le mie attività` come selezione effettiva.


## Prova manuale M7

1. Aprire una commessa attiva e verificare il pulsante `Documenti`.
2. Caricare un PDF inferiore a 10 MiB, selezionare classificazione e attività e verificare la comparsa in tabella.
3. Aprire il documento dal nome, modificarne classificazione, attività e descrizione e salvare.
4. Scaricare il documento e verificare che il browser non esponga alcun percorso fisico.
5. Aprire `/documenti` e verificare filtri, ricerca e ordinamento DataTables.
6. Accedere come collaboratore estraneo: il documento deve essere consultabile e scaricabile, ma non modificabile o eliminabile.
7. Accedere come uploader, responsabile, assegnatario dell’attività e socio e verificare la gestione autorizzata.
8. Provare un file `.php`, un eseguibile rinominato `.pdf`, un file vuoto e un file oltre 10 MiB: devono essere rifiutati.
9. Archiviare una commessa: i documenti devono restare leggibili, mentre il form di caricamento scompare.
10. Verificare che `var/storage/attachments` contenga nomi casuali e non sia raggiungibile dal document root.
11. Eliminare un documento dalla sua pagina e verificare la rimozione sia della riga sia del file fisico.
12. Verificare che il backup operativo includa database e directory documentale insieme.
