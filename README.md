# Betnero Core

Plugin di Betnero.it che sostituisce i plugin Crocoblock (JetEngine, JetSmartFilters, JetPopup, JetTabs, JetSearch e gli altri Jet*) e lo snippet 8 della toplist.

## Cosa fa

| Prima (Crocoblock) | Ora (Betnero Core) |
|---|---|
| JetEngine CCT "Casino" e "Scommesse" | Menu **Operatori** (Casino e Bookmaker), campi nativi WordPress |
| Ordine con la data di creazione (cct_created) | Colonna **Posizione**: si trascinano le righe |
| Query Builder 2 e 12, listing 163 e 41739 | Blocchi **Tabella casino** e **Tabella scommesse** |
| JetSmartFilters | Filtri dentro il blocco Tabella casino, senza ricaricare la pagina |
| Listing 971 e 53286, snippet 8 | **Toplist** automatica negli articoli e `[bn_toplist_sidebar]`, tipo per categoria in Operatori > Impostazioni |
| Listing 44362 "Singolo operatore Casino" | Blocco **Scheda operatore** (testata e testi della recensione) |
| 16 popup "Bottom Bar" di JetPopup | **Barra in basso** scelta nel riquadro "Betnero" di pagine e articoli |
| JetTabs accordion | Blocco Dettagli con stile **FAQ Betnero** (con dati FAQ per Google) |
| JetSearch | Ricerca di WordPress (già nel tema) |

## Passaggio da Crocoblock

Operatori > **Passaggio da Crocoblock**:

1. **Stato**: cosa manca prima di poter spegnere Crocoblock.
2. **Importa da JetEngine**: anteprima, poi importazione. Si può ripetere: aggiorna gli operatori già importati. Non cancella niente in JetEngine.
3. **Recensioni**: crea una bozza a blocchi di ogni recensione (la pagina originale non viene toccata).
4. **Inventario**: pagine, articoli e template che usano ancora widget Crocoblock, con cosa li sostituisce.

Finché non ci sono Operatori, i blocchi leggono ancora le query di JetEngine: il plugin si può attivare senza che le tabelle spariscano. Finché lo snippet 8 è attivo, la toplist automatica resta sua. Finché JetPopup è attivo, la barra in basso resta spenta (c'è un'opzione di prova).

## WP Rocket

Nella safelist di Remove Unused CSS: `.bnt-(.*)`, `.bns-(.*)`, `.bnb-(.*)`, `.is-style-bn-faq(.*)`.

## Aggiornamenti

Con Git Updater dal repository `d4rkcloud75/betnero-core` (branch `main`).
