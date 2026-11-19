#!/usr/bin/env python3
"""
Go-live Betnero: porta pagine, operatori e immagini dallo staging alla produzione
senza copiare il database (articoli e pronostici della produzione non si toccano).

Uso (variabili d'ambiente):
  ST_URL=https://staging...  ST_AUTH="utente:password applicazione"
  PR_URL=https://www.betnero.it  PR_AUTH="utente:password applicazione"   (senza PR_AUTH: solo prova, in lettura)

  python3 golive.py            prova: legge tutto e scrive solo il piano (golive-piano.json)
  python3 golive.py --applica  esegue davvero (immagini, operatori, pagine, impostazioni, home)

Le corrispondenze fra ID dello staging e della produzione vengono salvate in
golive-mappa.json: se lo script si interrompe, rilanciandolo riprende senza duplicare.

Requisiti: Betnero Core 0.5.0 o successivo attivo su entrambi i siti.
"""
import base64, json, os, re, sys, time, urllib.error, urllib.parse, urllib.request

APPLICA = '--applica' in sys.argv
ST = os.environ.get('ST_URL', '').rstrip('/')
PR = os.environ.get('PR_URL', '').rstrip('/')
ST_AUTH = os.environ.get('ST_AUTH', '')
PR_AUTH = os.environ.get('PR_AUTH', '')
DIR = os.path.dirname(os.path.abspath(__file__))
MAPPA_FILE = os.environ.get('GOLIVE_MAPPA', os.path.join(os.getcwd(), 'golive-mappa.json'))
PIANO_FILE = os.path.join(os.getcwd(), 'golive-piano.json')
# Pagine dello staging da non portare mai (prove e pagine di servizio).
SALTA_SLUG = set(os.environ.get('GOLIVE_SALTA', 'prova-tabelle,sample-page').split(','))

if not ST or not PR or not ST_AUTH:
    sys.exit('Imposta ST_URL, ST_AUTH e PR_URL (PR_AUTH solo per --applica).')
if APPLICA and not PR_AUTH:
    sys.exit('--applica richiede PR_AUTH.')

mappa = {'media': {}, 'operatori': {}, 'pagine': {}, 'url': {}}
if os.path.exists(MAPPA_FILE):
    mappa.update(json.load(open(MAPPA_FILE)))


def salva_mappa():
    json.dump(mappa, open(MAPPA_FILE, 'w'), indent=1, ensure_ascii=False)


def req(base, auth, path, method='GET', data=None, raw=None, headers=None, tentativi=5):
    url = path if path.startswith('http') else base + path
    h = {'User-Agent': 'betnero-golive', 'Accept': 'application/json'}
    if auth:
        h['Authorization'] = 'Basic ' + base64.b64encode(auth.encode()).decode()
    body = None
    if data is not None:
        body = json.dumps(data).encode()
        h['Content-Type'] = 'application/json'
    if raw is not None:
        body = raw
    h.update(headers or {})
    for t in range(tentativi):
        try:
            r = urllib.request.urlopen(urllib.request.Request(url, data=body, headers=h, method=method), timeout=180)
            b = r.read()
            if r.headers.get('Content-Type', '').startswith('application/json'):
                return json.loads(b), r.headers
            return b, r.headers
        except urllib.error.HTTPError as e:
            msg = e.read()[:400]
            if e.code in (400, 401, 403, 404, 409):
                raise RuntimeError(f'{method} {url} -> {e.code} {msg!r}')
            err = f'{e.code} {msg!r}'
        except Exception as e:  # rete instabile: si riprova
            err = repr(e)
        time.sleep(2 * (t + 1))
    raise RuntimeError(f'{method} {url} fallita dopo {tentativi} tentativi: {err}')


def st(path, **k):
    return req(ST, ST_AUTH, path, **k)[0]


def pr(path, **k):
    return req(PR, PR_AUTH, path, **k)[0]


def tutte(base, auth, path):
    out, pag = [], 1
    while True:
        sep = '&' if '?' in path else '?'
        try:
            d, h = req(base, auth, f'{path}{sep}per_page=100&page={pag}')
        except RuntimeError as e:
            if 'rest_post_invalid_page_number' in str(e):
                break
            raise
        out += d
        if pag >= int(h.get('X-WP-TotalPages', 1)):
            break
        pag += 1
    return out


def percorso(url):
    m = re.search(r'/wp-content/uploads/(.+)$', url or '')
    return m.group(1) if m else None


def log(*a):
    print(*a, flush=True)


# ---------------------------------------------------------------- controlli
log('Controllo Betnero Core 0.5.0 su entrambi i siti...')
st('/wp-json/betnero/v1/trasferimento/opzioni')
if PR_AUTH:
    pr('/wp-json/betnero/v1/trasferimento/opzioni')

piano = {'immagini_nuove': [], 'operatori_nuovi': [], 'pagine_aggiornate': [], 'pagine_nuove': [], 'pagine_saltate': []}

# ---------------------------------------------------------------- 1. immagini
log('1. Immagini')
st_media = tutte(ST, ST_AUTH, '/wp-json/wp/v2/media?context=edit&_fields=id,source_url,alt_text,title,media_details,mime_type')
pr_media = tutte(PR, PR_AUTH, '/wp-json/wp/v2/media?' + ('context=edit&' if PR_AUTH else '') + '_fields=id,source_url,media_details')
pr_by_path = {percorso(m['source_url']): m for m in pr_media}


def registra_url(sm, pm):
    """Corrispondenza degli indirizzi: originale e tutte le misure con lo stesso nome."""
    mappa['url'][percorso(sm['source_url'])] = percorso(pm['source_url'])
    ss = (sm.get('media_details') or {}).get('sizes') or {}
    ps = (pm.get('media_details') or {}).get('sizes') or {}
    for nome, s in ss.items():
        if nome in ps:
            mappa['url'][percorso(s['source_url'])] = percorso(ps[nome]['source_url'])


for m in st_media:
    p = percorso(m['source_url'])
    if str(m['id']) in mappa['media']:
        continue
    if p in pr_by_path:
        mappa['media'][str(m['id'])] = pr_by_path[p]['id']
        continue
    # Il file c'e' gia' in produzione (immagine non elencata, es. legata a una pagina non pubblica): niente doppione.
    try:
        req(PR, '', PR + '/wp-content/uploads/' + p, method='HEAD', tentativi=2)
        trovato = None
        if PR_AUTH:
            nome = os.path.splitext(os.path.basename(p))[0]
            for c in pr('/wp-json/wp/v2/media?context=edit&_fields=id,source_url,media_details&per_page=100&search=' + urllib.parse.quote(nome)):
                if percorso(c['source_url']) == p:
                    trovato = c
        if trovato:
            mappa['media'][str(m['id'])] = trovato['id']
        else:
            piano.setdefault('immagini_presenti_senza_id', []).append(p)
        continue
    except RuntimeError:
        pass
    piano['immagini_nuove'].append({'id': m['id'], 'file': p})
    if not APPLICA:
        continue
    dati, h = req(ST, '', m['source_url'])
    nome = os.path.basename(p)
    nuovo = pr('/wp-json/wp/v2/media', method='POST', raw=dati, headers={
        'Content-Type': m.get('mime_type') or h.get('Content-Type', 'application/octet-stream'),
        'Content-Disposition': f'attachment; filename="{nome}"'})
    pr(f'/wp-json/wp/v2/media/{nuovo["id"]}', method='POST', data={'alt_text': m.get('alt_text', ''), 'title': (m.get('title') or {}).get('raw', '')})
    mappa['media'][str(m['id'])] = nuovo['id']
    registra_url(m, nuovo)
    salva_mappa()
    log('   caricata', p, '->', percorso(nuovo['source_url']))
log('   immagini da caricare:', len(piano['immagini_nuove']))


def mid(v):
    """ID immagine dello staging -> ID in produzione."""
    try:
        return mappa['media'].get(str(int(v)), int(v))
    except (TypeError, ValueError):
        return v


def oid(v):
    try:
        return mappa['operatori'].get(str(int(v)), int(v))
    except (TypeError, ValueError):
        return v


def pid(v):
    try:
        return mappa['pagine'].get(str(int(v)), int(v))
    except (TypeError, ValueError):
        return v


def testo(s):
    """Indirizzi delle immagini spostate e dominio dello staging -> relativi."""
    if not isinstance(s, str):
        return s
    for a, b in sorted(mappa['url'].items(), key=lambda x: -len(x[0])):
        s = s.replace('/wp-content/uploads/' + a, '/wp-content/uploads/' + b)
    dom = ST.split('://', 1)[1]
    for sch in ('https://', 'http://', 'https:\\/\\/', 'http:\\/\\/'):
        s = re.sub(re.escape(sch + dom) + r'(?=["\'\s<]|$)', '/', s)  # link alla home: "/" e non vuoto
        s = s.replace(sch + dom, '')
    return s


# ---------------------------------------------------------------- 2. operatori
log('2. Operatori')
st_ops = st('/wp-json/betnero/v1/operatori')
pr_ops = {o['nome']: o for o in (pr('/wp-json/betnero/v1/operatori') if PR_AUTH else [])}
dettagli = {}
for o in st_ops:
    p = st(f'/wp-json/wp/v2/bn-operatori/{o["id"]}?context=edit&_fields=id,title,status,menu_order')
    dettagli[o['id']] = p
    if str(o['id']) in mappa['operatori']:
        continue
    if o['nome'] in pr_ops:
        mappa['operatori'][str(o['id'])] = pr_ops[o['nome']]['id']
        continue
    piano['operatori_nuovi'].append(o['nome'])
    if APPLICA:
        n = pr('/wp-json/wp/v2/bn-operatori', method='POST', data={'title': p['title']['raw'], 'status': p['status'], 'menu_order': p.get('menu_order', 0)})
        mappa['operatori'][str(o['id'])] = n['id']
        salva_mappa()
        log('   creato', o['nome'], '->', n['id'])
log('   operatori da creare:', len(piano['operatori_nuovi']))

# ---------------------------------------------------------------- 3. pagine (prima la corrispondenza, poi i contenuti)
log('3. Pagine')
st_pages = tutte(ST, ST_AUTH, '/wp-json/wp/v2/pages?context=edit&status=publish,draft,private,pending,future&_fields=id,slug,title,content,status,parent,menu_order,template,link')
pr_pages = {p['id']: p for p in tutte(PR, PR_AUTH, '/wp-json/wp/v2/pages?' + ('context=edit&status=publish,draft,private,pending,future&' if PR_AUTH else '') + '_fields=id,slug,link')}
da_creare = []
for p in st_pages:
    if p['slug'] in SALTA_SLUG:
        piano['pagine_saltate'].append(p['slug'])
        continue
    q = pr_pages.get(p['id'])
    if q and q['slug'] == p['slug']:
        mappa['pagine'].setdefault(str(p['id']), p['id'])
        piano['pagine_aggiornate'].append(p['id'])
    elif str(p['id']) in mappa['pagine']:
        piano['pagine_aggiornate'].append(p['id'])
    elif p['status'] == 'publish':
        da_creare.append(p)
        piano['pagine_nuove'].append({'id': p['id'], 'slug': p['slug']})
    else:
        piano['pagine_saltate'].append(p['slug'] or str(p['id']))
log('   da aggiornare:', len(piano['pagine_aggiornate']), ' nuove:', len(da_creare), ' saltate:', len(piano['pagine_saltate']))
if APPLICA:
    for p in da_creare:
        n = pr('/wp-json/wp/v2/pages', method='POST', data={'title': p['title']['raw'], 'slug': p['slug'], 'status': 'draft'})
        mappa['pagine'][str(p['id'])] = n['id']
        salva_mappa()
        log('   creata (bozza)', p['slug'], '->', n['id'])

json.dump(piano, open(PIANO_FILE, 'w'), indent=1, ensure_ascii=False)
if not APPLICA:
    log('\nProva finita. Piano in', PIANO_FILE, '- per eseguire: --applica')
    sys.exit(0)

# ---------------------------------------------------------------- 4. dati degli operatori
log('4. Dati degli operatori')


def meta_operatore(meta):
    out = {}
    meta = meta if isinstance(meta, dict) else {}
    for k, v in meta.items():
        if k in ('_bn_bozza_di',):
            continue
        if k == '_bn_logo':
            v = mid(v)
        elif k in ('_bn_gallery_sito', '_bn_gallery_app') and isinstance(v, list):
            v = [str(mid(x)) for x in v]
        elif k == '_bn_collegato':
            v = oid(v)
        elif k == '_bn_review_page':
            v = pid(v)
        else:
            v = testo(v)
        out[k] = v
    return out


for sid, nid in mappa['operatori'].items():
    m = st(f'/wp-json/betnero/v1/trasferimento/post/{sid}')['meta']
    pr(f'/wp-json/betnero/v1/trasferimento/post/{nid}', method='POST', data={'meta': meta_operatore(m)})
    log('   ', dettagli.get(int(sid), {}).get('title', {}).get('raw', sid), 'ok')

# ---------------------------------------------------------------- 5. contenuti delle pagine
log('5. Contenuti delle pagine')


def contenuto(raw):
    raw = re.sub(r'(<!-- wp:betnero/scheda-operatore \{[^}]*"operatore":)(\d+)', lambda m: m.group(1) + str(oid(m.group(2))), raw)
    raw = re.sub(r'(<!-- wp:image \{[^}]*"id":)(\d+)', lambda m: m.group(1) + str(mid(m.group(2))), raw)
    raw = re.sub(r'(wp-image-)(\d+)', lambda m: m.group(1) + str(mid(m.group(2))), raw)
    return testo(raw)


# Titolo SEO e meta description di Yoast restano quelli della produzione (Francesco li aggiorna li').
YOAST_PROD = ('_yoast_wpseo_title', '_yoast_wpseo_metadesc')


def meta_pagina(st_meta, pr_meta):
    out = {}
    st_meta = st_meta if isinstance(st_meta, dict) else {}  # nessun dato: WordPress restituisce [] invece di {}
    pr_meta = pr_meta if isinstance(pr_meta, dict) else {}
    st_meta = {k: v for k, v in st_meta.items() if k not in YOAST_PROD}
    pr_meta = {k: v for k, v in pr_meta.items() if k not in YOAST_PROD}
    for k, v in st_meta.items():
        if k == '_bn_operatore':
            v = oid(v)
        elif k == '_bn_barra' and str(v).isdigit():
            v = str(oid(v))
        else:
            v = testo(v)
        out[k] = v
    for k in pr_meta:  # chiavi presenti solo in produzione (es. il segnale di Elementor): si tolgono
        if k not in st_meta:
            out[k] = None
    return out


errori = []
SOLO = set(filter(None, os.environ.get('GOLIVE_SOLO', '').split(',')))  # es. GOLIVE_SOLO=newsletter,password-reset
for p in st_pages:
    if SOLO and p['slug'] not in SOLO:
        continue
    if str(p['id']) not in mappa['pagine'] or p['slug'] in SALTA_SLUG:
        continue
    nid = mappa['pagine'][str(p['id'])]
    try:
        dati = {'content': contenuto(p['content']['raw']), 'title': p['title']['raw'], 'status': p['status'],
                'parent': pid(p['parent']) if p['parent'] else 0, 'menu_order': p.get('menu_order', 0), 'template': p.get('template', '')}
        pr(f'/wp-json/wp/v2/pages/{nid}', method='POST', data=dati)
        sm = st(f'/wp-json/betnero/v1/trasferimento/post/{p["id"]}')['meta']
        pm = pr(f'/wp-json/betnero/v1/trasferimento/post/{nid}')['meta']
        pr(f'/wp-json/betnero/v1/trasferimento/post/{nid}', method='POST', data={'meta': meta_pagina(sm, pm)})
        log('   ', p['slug'], 'ok')
    except Exception as e:
        errori.append((p['slug'], str(e)))
        log('   ERRORE', p['slug'], e)

# ---------------------------------------------------------------- 6. impostazioni e home
log('6. Impostazioni e home')
pr('/wp-json/betnero/v1/trasferimento/opzioni', method='POST', data={'opzioni': st('/wp-json/betnero/v1/trasferimento/opzioni')})
s = st('/wp-json/wp/v2/settings')
if s.get('show_on_front') == 'page' and s.get('page_on_front'):
    pr('/wp-json/wp/v2/settings', method='POST', data={'show_on_front': 'page', 'page_on_front': pid(s['page_on_front'])})
    log('   home impostata:', pid(s['page_on_front']))

salva_mappa()
log('\nFatto.', 'Errori: ' + str(len(errori)) if errori else 'Nessun errore.')
for e in errori:
    log('  ', e)
