# Fixtures dimostrative

Le fixtures sono esplicite, deterministiche e non fanno parte del setup ordinario.

## Caricamento standard

```powershell
.\scripts\load-fixtures.ps1
```

Lo script esegue migrazioni e comando fixtures con ambiente `dev` esplicito. Funziona quindi anche dopo:

```powershell
.\scripts\start-server.ps1 -Mode fast
```

che usa temporaneamente `APP_ENV=prod`. Le variabili `APP_ENV` e `APP_DEBUG` presenti prima del caricamento vengono ripristinate al termine.

Per il database di test è disponibile:

```powershell
.\scripts\load-fixtures.ps1 -Environment test
```

Il comando applicativo resta protetto fuori da `dev/test`; lo script ordinario non usa mai `--force`.

## Profilo standard M5 Hotfix 4

Il dataset contiene:

- 8 utenti: 2 soci e 6 collaboratori;
- 10 clienti;
- 30 commesse;
- 200 attività;
- 600 registrazioni ore concluse;
- 240 spese;
- 120 incassi.

Distribuzione delle registrazioni sulle attività:

| Attività | Registrazioni per attività | Totale registrazioni |
|---:|---:|---:|
| 20 | 0 | 0 |
| 40 | 1 | 40 |
| 60 | 2 | 120 |
| 50 | 4 | 200 |
| 30 | 8 | 240 |
| **200** |  | **600** |

La distribuzione è mescolata deterministicamente tra le commesse; non dipende da casualità o dall’ordine del database.

## Assegnatari e autori delle ore

L’assegnatario identifica la persona responsabile dell’attività. Ogni registrazione ore identifica invece la persona che ha realmente lavorato.

Il profilo include:

- attività assegnate a collaboratori;
- attività assegnate a soci;
- ore registrate dall’assegnatario;
- ore registrate da persone diverse dall’assegnatario;
- attività con fino a cinque persone differenti;
- ore fatturabili e non fatturabili;
- tariffe risolte da attività, commessa o utente e congelate nella registrazione.

Gli intervalli sono costruiti senza sovrapposizioni per lo stesso utente.

## Riconciliazione e sicurezza

Una nuova esecuzione:

- riutilizza utenti, clienti, commesse, attività, spese e incassi identificati;
- sostituisce soltanto le registrazioni riconoscibili come fixtures correnti;
- elimina le registrazioni del precedente profilo M5 riconoscibili dal testo standard;
- conserva registrazioni manuali o non riconducibili alle fixtures;
- non crea duplicati del dataset gestito.

I conteggi esatti 30/200/600 si riferiscono a un database pulito o contenente soltanto fixtures non modificate. Eventuali registrazioni manuali aggiuntive vengono correttamente conservate e si sommano alle 600 registrazioni demo.

## Credenziali

```text
Username: demo.socio
Password: Demo-accesso-2026!
```

La password comune serve esclusivamente al dataset dimostrativo locale.
