# Changelog

## 0.1.9
- Recensioni, accessibilità: le immagini delle gallerie "Il sito web" e "App" hanno un testo alternativo (se manca nella libreria media) e i link un'etichetta ("Apri Leovegas, il sito web, immagine 1 in una nuova scheda"). Corregge il controllo "albero di accessibilità" di Lighthouse (Agentic browsing).

## 0.1.8
- Recensioni, velocità su mobile: il logo della testata e la prima immagine della galleria "Il sito web" si caricano subito con priorità alta invece che in differita (erano l'elemento principale della pagina, LCP, a 4,5 secondi su mobile).

## 0.1.7
- Cache delle pagine: dopo una modifica agli operatori (salvataggio, ordine, cestino) o alle Impostazioni Betnero si svuota anche la cache di Velox Optimizer (oltre a WP Rocket e LiteSpeed Cache, se attivi).
- Lo svuotamento parte un minuto dopo l'ultima modifica: più salvataggi o trascinamenti di fila ne fanno partire uno solo.

## 0.1.6
- Recensioni a blocchi: le tabelle nei testi (livelli VIP, metodi di pagamento, strumenti di gioco responsabile) diventano blocchi Tabella invece di blocchi HTML.
- Tolti gli shortcode [su_accordion] rimasti spaiati nel testo (Gioco Digitale, Sisal).

## 0.1.5
- Recensioni a blocchi: i testi diventano blocchi veri (paragrafi, titoli, elenchi) invece di un blocco "Classico".
- Gli accordion di Shortcodes Ultimate ([su_accordion], [su_spoiler]) diventano blocchi FAQ Betnero (con i dati strutturati FAQPage) o elenchi se non hanno testo.
- Punti di forza e Difetti in due colonne affiancate, con spunta verde e croce rossa; ignorato il "Testo finto" rimasto negli spoiler.
- Recensioni: pulsante "Rifai" per rigenerare il contenuto della pagina a blocchi (titolo, indirizzo e stato restano).
- API REST betnero/v1 (operatori, campi di un operatore, crea o rifai la pagina a blocchi) per chi ha i permessi di Editor.

## 0.1.4
- Toplist: ogni categoria vale prima della sua categoria madre (Pronostici resta scommesse anche con Blog impostato su Nessuna).
- Sidebar: come lo snippet 8, mostra sempre una toplist (casino se l'articolo non ne ha una nel testo).

## 0.1.3
- Barre in basso: "Sportitaliabet Casino" non viene più scambiato per scommesse; tra operatori dello stesso marchio vince quello pubblicato (Eplay24 Scommesse invece di "Test - Eplay").
- Le barre messe dall'importazione si ricalcolano a ogni importazione; quelle scelte a mano nella pagina non vengono toccate.
- Resoconto: avviso se la barra punta a un operatore in bozza.

## 0.1.2
- Importazione: legge le condizioni di visualizzazione di JetPopup ("mostra sulla pagina X"), che è il modo in cui sono agganciate le barre "Bottom Bar".

## 0.1.1
- Importazione: trova i popup "Bottom Bar" agganciati agli elementi delle pagine Elementor (non solo alle impostazioni pagina).
- Operatori collegati: preferisce quelli pubblicati e corregge i collegamenti a operatori in bozza (es. Eplay Casino era collegato a "Test - Eplay").
- Pagina recensione trovata anche se è stata spostata sotto un'altra pagina (es. DomusBet).

## 0.1.0
- Prima versione: Operatori, migrazione da JetEngine, blocchi tabella, toplist e scheda operatore, toplist automatica negli articoli, barra in basso, stile FAQ, inventario Crocoblock.
