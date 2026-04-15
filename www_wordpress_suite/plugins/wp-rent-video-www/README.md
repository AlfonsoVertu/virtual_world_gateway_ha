# WWW Video Tickets – Documentazione tecnica

Questo repository contiene l'installazione WordPress con il plugin personalizzato **WWW Video Tickets** per la vendita e la fruizione controllata di contenuti video HLS su CloudFront. La documentazione seguente riassume il comportamento effettivo del codice, le dipendenze principali e i flussi operativi osservati nel sorgente del plugin.

## Struttura del progetto

```
wp-content/
└── plugins/
    └── www-video-tickets/
        ├── assets/          # Risorse frontend (player.js)
        ├── inc/             # Classi PHP organizzate per responsabilità
        ├── readme.txt       # Documentazione WordPress del plugin
        ├── uninstall.php    # Pulizia opzionale dei dati
        └── www-video-tickets.php  # Bootstrap del plugin
```

Sono presenti inoltre script SQL in `db/migrations/` per mantenere l'allineamento dello schema quando non si usa l'installazione automatica del plugin.

## Panoramica funzionale

Il plugin implementa un sistema di licenze legato a prodotti WooCommerce che concede all'acquirente un numero finito di visioni all'interno di una finestra temporale. Ogni licenza autorizza la riproduzione di flussi HLS su CloudFront attraverso **Signed Cookies** e un JWT di sessione (`CF-Auth`). Il player JavaScript contabilizza i secondi effettivamente guardati, gestisce le interruzioni pubblicitarie e sincronizza i progressi con WordPress via REST API. 【F:wp-content/plugins/www-video-tickets/www-video-tickets.php†L1-L86】【F:wp-content/plugins/www-video-tickets/assets/player.js†L1-L303】

## Attivazione e bootstrap

* `www-video-tickets.php` registra gli hook di attivazione, inizializza i componenti e collega gli eventi WooCommerce `order_status_processing` / `order_status_completed` per emettere le licenze. 【F:wp-content/plugins/www-video-tickets/www-video-tickets.php†L1-L76】
* L'attivatore (`inc/class-activator.php`) crea tre tabelle:
  * `www_vt_licenses`: anagrafica licenze con secondi residui, visioni disponibili e percorso CloudFront.
  * `www_vt_progress`: progressi di visualizzazione per sessione.
  * `www_vt_ads_views`: tracciamento delle visualizzazioni delle creatività pubblicitarie.
  Gli hook di (de)attivazione curano anche l'aggiornamento dell'opzione `www_vt_db_ver` e il flush delle rewrite rules. 【F:wp-content/plugins/www-video-tickets/inc/class-activator.php†L1-L66】
* Esiste uno script SQL (`db/migrations/2025-11-01_www_vt_progress.sql`) che replica la creazione/alterazione delle tabelle in contesti dove non è possibile eseguire `dbDelta`. 【F:db/migrations/2025-11-01_www_vt_progress.sql†L1-L56】

## Configurazione e impostazioni

La schermata **Impostazioni → WWW Video Tickets** permette di definire:

* Dominio CloudFront (deve essere un CNAME sotto il dominio principale per consentire i cookie).
* Key Pair ID e chiave privata RSA per i Signed Cookies.
* TTL dei cookie e segreto JWT.
* Parametri di tracking: soglia consumo progressi, intervalli heartbeat/posizione, frequenza e durata consigliata delle ADS.

La pagina include una guida operativa completa per la preparazione dei contenuti su AWS (S3, CloudFront, AES-128, ecc.). Sono presenti avvisi amministrativi se mancano credenziali o se il dominio CloudFront non è compatibile con i cookie. 【F:wp-content/plugins/www-video-tickets/inc/class-settings.php†L1-L206】

## Metadati prodotto WooCommerce

Ogni prodotto può essere configurato con:

* Numero massimo di visioni, durata finestra (in ore) e minuti totali acquistati.
* Path prefix dell'asset HLS (usato per scopo e per generare la playlist di default).
* Flag e override per la riproduzione di ADS (intervallo, durata consigliata, durata video nota).
* Parametri AES-128: chiave esadecimale a 16 byte (`Key HEX`) e URI della chiave da inserire nella playlist.

Questi metadati vengono salvati tramite metabox dedicato e validati (es. lunghezza della chiave). 【F:wp-content/plugins/www-video-tickets/inc/class-product-meta.php†L1-L86】

## Lifecycle delle licenze

* Alla conferma di un ordine WooCommerce viene creato un record in `www_vt_licenses` con visioni, secondi e scadenza calcolati dai metadati del prodotto. Il percorso (`path_prefix`) è normalizzato (`/videos/123/`). 【F:wp-content/plugins/www-video-tickets/www-video-tickets.php†L47-L74】
* Il data layer (`class-license-store.php`) fornisce metodi per:
  * Creare una licenza e prevenire duplicati per stesso ordine/utente/prodotto.
  * Recuperare la licenza attiva più recente per un dato utente/prodotto.
  * Registrare il consumo di una sessione con transazioni SQL, garantendo che la visione venga scalata una sola volta. 【F:wp-content/plugins/www-video-tickets/inc/class-license-store.php†L1-L73】
* L'helper `www_vt_user_license` verifica scadenza, visioni e secondi residui prima di concedere l'accesso. 【F:wp-content/plugins/www-video-tickets/inc/helpers.php†L1-L61】

## Emissione cookie e JWT

Lo shortcode `[video_watch]` avvia tre passaggi fondamentali:

1. Verifica autenticazione utente e licenza valida.
2. Genera o riutilizza un JWT HS256 (`CF-Auth`) contenente `license_id`, `session_id`, `product_id`, `path_prefix`, `nbf`, `exp` e lo memorizza come cookie.
3. Firma una policy CloudFront che copre `https://<CNAME>/<path_prefix>*`, restituendo i tre cookie necessari (`CloudFront-Policy`, `CloudFront-Signature`, `CloudFront-Key-Pair-Id`).

Le cookie vengono settate con `SameSite=None`, `Secure`, dominio coerente col CNAME e, per default, `HttpOnly` ad eccezione di `CF-Auth` (configurabile via filtro). 【F:wp-content/plugins/www-video-tickets/inc/class-shortcode.php†L1-L229】

## Player JavaScript

`assets/player.js` carica dinamicamente `hls.js` (con credenziali) se il browser non supporta HLS nativo e adotta queste logiche:

* Avvio on-demand: l'HLS viene caricato solo al primo play.
* Tracking second-by-second con persistenza locale (localStorage) e sincronizzazione periodica via `POST /view/progress`.
* Ripristino della posizione tramite `GET /view/position`.
* Gestione ADS: richiesta creatività via `/ads/list`, blocco seek durante la pubblicità, overlay countdown, fallback in caso di errori e chiamata `/ads/mark` a fine riproduzione.
* Invio progressi finale (`sendBeacon` o XHR sincrono) all'evento `beforeunload`.

L'implementazione impedisce salti durante l'ADS e ripristina lo stream principale mantenendo le credenziali CloudFront. 【F:wp-content/plugins/www-video-tickets/assets/player.js†L1-L395】

## API REST esposte

Namespace: `www-vt/v1`.

* `POST /view/start` – Consuma una visione (se non già conteggiata per la sessione) e restituisce i secondi residui. Richiede token JWT (`token` nel payload). 【F:wp-content/plugins/www-video-tickets/inc/class-rest.php†L1-L46】
* `POST /view/progress` – Aggiorna secondi guardati e posizione, scala i secondi residui rispettando soglie configurate e rate limiting (basato su IP). Usa cookie `CF-Auth`. 【F:wp-content/plugins/www-video-tickets/inc/class-rest-progress.php†L1-L205】【F:wp-content/plugins/www-video-tickets/inc/class-rest-progress.php†L205-L316】
* `GET /view/position` – Recupera l'ultima posizione salvata per prodotto/sessione. 【F:wp-content/plugins/www-video-tickets/inc/class-rest-progress.php†L205-L316】
* `GET /ads/list` – Restituisce una creatività compatibile con il path del film, evitando ripetizioni per sessione e rispettando eventuali esclusioni. 【F:wp-content/plugins/www-video-tickets/inc/class-rest-ads.php†L1-L161】
* `POST /ads/mark` – Segna un'ADS come vista (licenza/sessione), prevenendo doppi conteggi. 【F:wp-content/plugins/www-video-tickets/inc/class-rest-ads.php†L161-L226】

Tutti gli endpoint disabilitano la cache HTTP e usano JWT per autenticare la sessione.

## Gestione delle chiavi AES

Il rewrite `/keys/<id>.key` è servito da `class-keys-endpoint.php` che:

* Supporta ID numerici o slug di prodotto.
* Applica CORS ristretto (`https://libertaeazione.it`) e gestisce preflight.
* Verifica il JWT `CF-Auth`, la licenza e lo scope (`path_prefix`).
* Impone rate limit configurabile via filtri.
* Restituisce la chiave AES (16 byte) come `application/octet-stream` disabilitando la cache.

L'endpoint bypassa i redirect canonici di WordPress per evitare `301` sulle URL `.key`. 【F:wp-content/plugins/www-video-tickets/inc/class-keys-endpoint.php†L1-L123】

## Backend amministrativo

* **Menu “Video Tickets”**: elenca le licenze, consente modifica rapida di visioni/secondi/scadenza e la creazione manuale di nuove licenze con validazioni puntuali. Supporta ricerca e paginazione. 【F:wp-content/plugins/www-video-tickets/inc/class-admin-licenses.php†L1-L223】
* **Report ADS**: filtro per prodotto/ADS/intervalli data e visualizzazione delle impression, con paginazione. 【F:wp-content/plugins/www-video-tickets/inc/class-admin-ads-report.php†L1-L146】
* **Custom Post Type “ADS Video”**: gestisce catalogo delle creatività con path, durata e playlist. 【F:wp-content/plugins/www-video-tickets/inc/class-ads.php†L1-L86】

## Disinstallazione

`uninstall.php` rimuove le opzioni e, se è definita la costante `WWW_VT_DELETE_DATA`, elimina le tabelle (`www_vt_licenses`, `www_vt_progress`, `www_vt_ads_views`) e i post del CPT ADS. 【F:wp-content/plugins/www-video-tickets/uninstall.php†L1-L28】

## Considerazioni operative e sicurezza

* Assicurarsi che CloudFront utilizzi un CNAME sotto il dominio principale per consentire ai cookie `Set-Cookie` di essere accettati dal browser. 【F:wp-content/plugins/www-video-tickets/inc/class-settings.php†L200-L206】
* La chiave privata RSA e il segreto JWT devono essere forniti tramite costanti (`WWW_VT_PRIVATE_KEY`, `WWW_VT_JWT_SECRET`) o attraverso le impostazioni, evitando versionamento accidentale.
* I cookie vengono impostati con `Secure` e `SameSite=None`; mantenere il sito su HTTPS.
* Valutare il rate limiting degli endpoint chiave tramite i filtri `www_vt_keys_endpoint_rate_limit` e `www_vt_keys_endpoint_rate_ttl`.

## Shortcode disponibili

* `[video_watch product_id="123" playlist="/videos/123/master.m3u8" width="100%" maxwidth="960px"]`
* `[www_vt_ads id="321" width="100%" maxwidth="640px"]`

Il primo inietta il player principale, il secondo consente un'anteprima frontend di una singola creatività ADS con supporto `hls.js` e note di durate. 【F:wp-content/plugins/www-video-tickets/inc/class-shortcode.php†L16-L143】

## File di supporto

* `readme.txt`: descrizione sintetica per l'ecosistema WordPress, utile come quick start. 【F:wp-content/plugins/www-video-tickets/readme.txt†L1-L69】
* `assets/player.js`: logica frontend documentata in questo README. 【F:wp-content/plugins/www-video-tickets/assets/player.js†L1-L395】

---

Questa documentazione è derivata direttamente dal codice presente nel repository ed è pensata per facilitare manutenzione, audit di sicurezza e onboarding di nuovi sviluppatori.
