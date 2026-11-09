# Changelog

## 0.4.2
- Barra in basso: tolta la X per chiuderla, resta sempre visibile.
- Il pulsante "torna su" si alza sopra la barra solo nelle pagine che ce l'hanno (altezza misurata, desktop e mobile).

## 0.4.1
- Recensioni di operatori con bonus casino e bonus sport (campo "collegato"): nella scheda in alto si vedono entrambi i bonus, con un pulsante ciascuno; nella barra in basso due righe, una per bonus. Come in produzione, per essere compliant.

## 0.4.0
- Velox: lo script di Cookie Notice non viene piu' ritardato fino alla prima interazione, cosi' il banner dei cookie compare subito.

## 0.3.9
- Pagine fatte con Elementor: ripristinati gli stili dei riquadri JetEngine (pulsanti "Recensione" arancione e "Vai al sito" blu, foto piccola e rotonda nel "Parere dell'esperto"), che stavano nel tema child di Hello. Tolto il caricamento del CSS delle tabelle introdotto nella 0.3.8, che non serviva.

## 0.3.8
- Pagine fatte con Elementor: caricati gli stili delle tabelle di Betnero Core, che servono ai riquadri "Parere dell'esperto" (foto rotonda piccola accanto al testo).

## 0.3.7
- Misura del tempo di generazione: con ?bnc_timing=1 la pagina scrive in fondo un commento con tempo PHP, numero di query, plugin attivi e memoria.

## 0.3.6
- Pagine a blocchi piu' leggere: non vengono caricati CSS e JS di Elementor, Pro Elements, Happy Addons, Crocoblock, MetForm, WP Social, Contact Form 7, WP Ultimate Review, GutenKit, Sticky Header (ne' jQuery, se nessuno lo usa); niente popup di Elementor. Le pagine fatte con Elementor restano come sono. Si controlla con ?bnc_assets=debug.

## 0.3.5
- Scheda recensione: il titolo ("Recensione Snai Casino") e' l'H1 della pagina, se il testo non ne ha gia' uno.

## 0.3.4
- Apertura e chiusura animate per tutti i blocchi Dettagli (non solo le FAQ) e per l'indice dell'Academy.
- Sezione "Guida Betnero" della home: aprendo una voce si chiudono le altre.
- Un clic su un link nel titolo di una voce segue il link senza aprirla.

## 0.2.9
- Sidebar degli articoli uguale al sito originale: per le scommesse "I migliori bookmaker" (tutti i bookmaker in tabella, logo e "Vai al sito"); per i casino "Ultimi Casinò aggiunti" (l'ultimo casino aggiunto) e il banner LeoVegas (filtro bnc_sidebar_banner per cambiarlo).

## 0.2.8
- Classifica a schede: su mobile i filtri stanno su una sola riga che scorre in orizzontale (su desktop restano come prima).
- La pillola del selettore Casinò/Scommesse si ricalcola quando i pulsanti cambiano larghezza.

## 0.2.7
- Dati strutturati FAQPage: solo le voci che sono vere domande (finiscono con "?"); le fisarmoniche di contenuto delle pagine convertite da Elementor (es. sezioni per provider) non entrano più nello schema.

## 0.2.6
- FAQ: anche il cassetto "Le domande più frequenti" (Dettagli con classe bn-faqbox) si apre e si chiude animato; le FAQ al suo interno sono esclusive (aprendone una si chiudono le altre).

## 0.2.5
- Classifica a schede: opzione "Cosa mostrare" (casino e scommesse con selettore, solo casino, solo scommesse).
- API REST per convertire le pagine Elementor: /converti (HTML -> blocchi), /pagine/<id>/blocchi (salva i blocchi e spegne Elementor per la pagina, con copia di sicurezza), /pagine/<id>/elementor (torna alla versione Elementor).

## 0.2.4
- Operatori: nuove opzioni reali per "Tipologia bonus" (bonus sul deposito, free spin, cashback, fun bonus, Crazy Time / Aviator) e "Pagamenti" (PayPal, Visa / Mastercard, PostePay, Skrill, Neteller, Paysafecard, Apple Pay, Google Pay, bonifico / Trustly, contanti in punto vendita) al posto dei valori segnaposto di JetEngine.
- Classifica a schede: filtri "Tipologia bonus" e "Pagamenti" come menu a tendina (solo le opzioni che almeno un casino ha) e pulsante "Rimuovi filtri".
- API REST: POST /betnero/v1/operatori/<id>/campi per scrivere i campi di un operatore (permesso di modifica dell'operatore).

## 0.2.3
- FAQ (stile "FAQ Betnero"): apertura e chiusura animate in tutto il sito; nella hero della home aprendo una domanda si chiudono le altre (contenitore .bn-hh-faq o attributo data-bn-exclusive).
- Classifica a schede: la pillola del selettore scivola sull'opzione scelta, la lista cambia con una dissolvenza e le schede entrano una dopo l'altra; i filtri fanno sparire e ricomparire le schede con una dissolvenza; leggero sollevamento della scheda al passaggio del mouse.
- Con "riduci movimento" attivo nel sistema operativo tutto cambia senza animazioni.
- Lo script delle FAQ è escluso dal ritardo di Velox Optimizer, come quello della classifica.

## 0.2.2
- Classifica a schede: lo script del selettore Casinò/Scommesse e dei filtri è escluso dal ritardo degli script di Velox Optimizer, così funziona già al primo clic.

## 0.2.1
- Classifica a schede: selettore e filtri non prendono più i bordi rosa del reset del vecchio tema; avatar del parere non più schiacciato su mobile.
- Numeri del sito: etichette su due righe senza sovrapporsi.

## 0.2.0
- Nuovo blocco "Classifica a schede" (betnero/classifica): casino e bookmaker in schede con voto, badge (senza deposito, SPID, bonus multipla), parere dell'esperto, selettore Casinò/Scommesse e filtri rapidi.
- Nuovo blocco "Podio operatori" (betnero/podio): i primi 3 in schede compatte, pensato per la hero blu della home.
- Nuovo blocco "Numeri del sito" (betnero/numeri): operatori, recensioni, pronostici e guide dell'Academy contati in automatico (aggiornati al massimo ogni ora).

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
