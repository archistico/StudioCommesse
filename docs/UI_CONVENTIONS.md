# Convenzioni dell'interfaccia

Il layout applicativo rende il titolo, l'eventuale blocco page_summary, la barra page_actions e infine page_body. Le card di riepilogo presentano gli indicatori essenziali; le informazioni di approfondimento possono essere richiuse tramite details e devono seguire gli elenchi quando sono secondarie.

La barra delle azioni distribuisce i pulsanti sull'intera larghezza della riga, senza spazi vuoti laterali. Ogni riga contiene al massimo sei azioni, ridotte quando la larghezza disponibile lo richiede; le righe sono bilanciate e i pulsanti hanno un'altezza minima di 48 px. I pulsanti primari ricevono più spazio; data-action-priority="3" aumenta il peso delle operazioni più frequenti, mentre data-action-priority="1" assegna il peso normale. La dashboard privilegia registrazione ore e attività. Il ridimensionamento conserva ordine, moduli, token CSRF e collegamenti.

Liste e tabelle occupano tutta la larghezza disponibile e non vanno inserite in colonne affiancate. Ogni tabella usa data-datatable, intestazioni con scope="col" e un contenitore table-responsive. Sui piccoli schermi DataTables nasconde le colonne secondarie e consente di aprire i dettagli dalla prima cella; la prima colonna conserva sempre l'identità del record. Date, durate e importi mantengono il valore di ordinamento data-order.

I filtri complessi usano details.card.section-filters. I valori restano nei controlli dopo l'applicazione; la barra delle azioni resta visibile anche con i filtri chiusi. Le modifiche dei filtri non salvano dati.

I form usano form-page per limitare la lunghezza delle righe su desktop e col-12 col-md-6 per coppie correlate, ad esempio inizio/fine e stima/residuo. Testi lunghi e descrizioni occupano una riga intera. I campi opzionali non devono spostare una metà della coppia su un'altra riga. La card-footer.form-actions, dentro il form, contiene Annulla a sinistra e il pulsante primario di salvataggio a destra. Le operazioni distruttive restano separate.

Una vista globale deve offrire le creazioni consentite dal ruolo. Se il filtro identifica già la commessa o l'attività, il pulsante apre direttamente il form contestuale. Altrimenti si presenta una scelta esplicita; l'unica commessa disponibile può essere preselezionata. Commesse archiviate e attività di altre commesse vengono rifiutate anche dal server. La visibilità del pulsante non sostituisce mai il controllo del controller o del voter.

Un arresto del timer sotto il minuto apre una scelta esplicita nel dettaglio dell'attività. Lascia continuare e l'assenza di interazione non modificano la registrazione. Annulla registrazione invia un POST separato: il token e l'identificativo devono corrispondere al timer proprio ancora attivo. La registrazione viene rimossa soltanto dopo questa conferma e l'evento resta nell'audit.
