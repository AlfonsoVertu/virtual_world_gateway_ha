=== WWW Video Tickets ===
Contributors: workingwithweb
Tags: woocommerce, video, cloudfront, s3, hls, encryption, jwt
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Vendi accessi a video HLS su CloudFront: X visioni in Y ore. Cookie firmati, JWT di sessione, chiave AES-128 servita da WordPress, player senza pre-buffer.

== Descrizione ==
- Ogni ordine crea una licenza: `remaining_views=X`, `expires_at=now+Yh`, `path_prefix`.
- Emissione **CloudFront Signed Cookies** su `path_prefix/*` e cookie `CF-Auth` (JWT HS256).
- Endpoint REST che conta la prima riproduzione per sessione e riduce le visioni.
- Endpoint `/keys/{product_id}.key` che restituisce la **chiave AES-128** (16 byte) solo se licenza valida e token coerente.
- Shortcode `[video_watch product_id="123" playlist="/videos/123/master.m3u8"]`.
- Console amministrativa per gestione licenze.

== Installazione ==
1. Copia la cartella `www-video-tickets` in `wp-content/plugins` e attiva il plugin.
2. Requisiti: WooCommerce attivo, distribuzione CloudFront con **Origin Access Control (OAC)** verso S3 privato.
3. Configura **Impostazioni → WWW Video Tickets**:
   - **CloudFront Domain**: usa un **CNAME sotto il tuo dominio** (es. `video.tuodominio.it`), non `dxxxx.cloudfront.net`, per consentire i cookie.
   - **Key Pair ID**: ID della chiave pubblica nel Key Group CloudFront.
  - **Private Key (PEM)**: chiave privata corrispondente (meglio via `define('WWW_VT_PRIVATE_KEY','...')`).
   - **TTL cookie (min)**: durata dei cookie di sessione.
   - **JWT secret**: stringa segreta lunga per HS256.
4. In **Prodotti → WWW Video Tickets**:
   - **Max visioni (X)** e **Finestra (ore) (Y)**.
   - **Path prefix HLS** (es. `/videos/123/` con dentro `master.m3u8` e segmenti).
   - **Key HEX (32 char)** e **Key URI** (es. `/keys/123.key`) da usare nella playlist.
5. **CloudFront behaviors**:
   - `/videos/*` → origin S3 (OAC), **Restrict Viewer Access** con Signed Cookies, cache breve sulle playlist e più lunga sui segmenti.
   - `/keys/*` → origin WordPress, **cache disabilitata**, inoltra **tutti i cookie**.
6. Nella pagina di visione usa:

[video_watch product_id="123" playlist="/videos/123/master.m3u8"]

== Come funziona ==
- L’utente con licenza valida apre la pagina: il server emette i cookie firmati CloudFront e un JWT di sessione (`CF-Auth`).
- Il player non carica nulla finché non si preme Play.
- Al primo Play la pagina chiama l’endpoint REST che **scala 1 visione** se quella sessione non è già stata conteggiata.
- Le richieste HLS a CloudFront sono autorizzate dai cookie; la chiave AES è servita dall’endpoint `/keys/*` solo se il JWT è valido e la licenza non è scaduta.

== Sicurezza ==
- S3 deve essere **privato**. Accesso solo da CloudFront con OAC.
- Senza DRM non puoi bloccare il download al 100%. AES-128 + cookie riduce il rischio ma non lo elimina.
- Imposta CORS stretti, disabilita embed indesiderati, considera watermark dinamico.

== FAQ ==
= Perché serve un CNAME sotto il mio dominio? =
I browser accettano cookie solo per lo stesso dominio o un suo superdominio. Se il player è su `www.tuodominio.it` e la CDN su `video.tuodominio.it`, i Set-Cookie funzionano. Non funzionano verso `dxxxx.cloudfront.net`.

= Posso usare URL firmati anziché cookie? =
Per segmenti HLS è scomodo firmare ogni URL. I Signed Cookies coprono tutto il path.

= Blocca il download? =
Solo un DRM (Widevine/FairPlay/PlayReady) lo blocca in modo robusto. Questo plugin rende il ripping difficile.

== Changelog ==
= 1.0.0 =
- Prima versione completa con AES-128, console licenze, cookie firmati e conteggio visioni.

== Upgrade Notice ==
- Nessuna migrazione nota. Per disinstallare e rimuovere i dati definisci `define('WWW_VT_DELETE_DATA', true);` prima di disinstallare.


Pronto all’uso.


Inserisci in HLS:
#EXT-X-KEY:METHOD=AES-128,URI="https://<CNAME_CF>/keys/<PRODUCT_ID>.key"


Carica il video in S3, pubblica tramite CloudFront su /videos/<PRODUCT_ID>/master.m3u8.


Attiva prodotto e shortcode.
