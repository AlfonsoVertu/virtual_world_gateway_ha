# WWW WordPress Suite

<div align="center">
  <img src="https://raw.githubusercontent.com/AlfonsoVertu/virtual_world_gateway_ha/master/vwg_logo.png" width="80" />
  &nbsp;&nbsp;&nbsp;
  <img src="https://raw.githubusercontent.com/AlfonsoVertu/virtual_world_gateway_ha/master/www_logo.png" width="80" />
</div>

---

Un'istanza WordPress pre-configurata che pre-carica al primo avvio un'intera suite di plugin professionali sviluppati (o selezionati) da **Working With Web**. Un ambiente pulito e pronto all'uso, ideale per essere impiegato come hub avanzato o e-commerce.

---

## Plugin pre-installati

Questa versione include nella cartella `/wp-content/plugins/` i seguenti plugin. Essi **non** sono attivati di default per evitare impatti prestazionali. Potrai attivarli manualmente dalla tua bacheca WordPress una volta effettuato il login:

- **Working With Image Converter**: Conversione intelligente di formati immagine.
- **Working With TOC**: Generazione automatica di Table of Contents per gli articoli.
- **Working With JS/CSS/HTML Minify**: Ottimizzatore prestazioni e minificatore del codice frontend.
- **WWW WP Woo Multivendor**: Piattaforma per generare portali fornitori multi-tenant.
- **WWW DB Malware Analysis**: Analizzatore di pattern malevoli su file e database.
- **WP Rent Video WWW**: Piattaforma di noleggio VOD, film e videocorsi.
- **Blog2Social**: Integrazione avanzata tra blog e social network.
- **Shortcoder**: Utilità standard per la generazione rapida di shortcode personalizzati.

---

## Configurazione dell'add-on

| Parametro | Descrizione |
|:---|:---|
| `db_host` | Host del database MariaDB (usare `core-mariadb` con l'add-on ufficiale). |
| `db_name` | Nome del database (es. `www_suite`). |
| `db_user` / `db_pass` | Credenziali di accesso a MariaDB. |
| `wp_domain` | Dominio o indirizzo IP (es. `192.168.1.56`). |
| `wp_admin_user` / `wp_admin_password` | Credenziali per l'amministratore del sito WordPress. |
| `wp_admin_email` | Email associata all'account amministratore. |

**Porte esposte:**
| Porta | Utilizzo |
|:---|:---|
| `8082` | Interfaccia Web di WordPress |

---

## Primo avvio

1. Assicurati che un add-on MariaDB sia avviato.
2. Compila la configurazione (usa una password sicura per `db_pass` e `wp_admin_password`).
3. Avvia l'add-on. Il bootstrap iniziale impiegherà alcuni minuti per installare il core e copiare l'intera suite di plugin.
4. Apri l'interfaccia all'indirizzo `http://<ip-ha>:8082/wp-admin`.
5. Accedi e vai nella sezione **Plugin** per attivare le estensioni che desideri usare.

---

## Troubleshooting

**Schermata bianca o "Error establishing a database connection"**
→ L'host del database o le credenziali non sono corretti. Se MariaDB non si è ancora attivato, aspetta qualche minuto e riavvia l'add-on.

**Alcuni plugin WooCommerce (es. vendite / noleggio) restituiscono errori critici**
→ I plugin `www-wp-woo-multivendor` e `wp-rent-video-www` dipendono dal plugin gratuito **WooCommerce**. Installalo e attivalo dallo store dei plugin normale prima di attivare queste estensioni proprietarie.

---

## Crediti

Suite curata e sviluppata da [Alfonso Vertucci](https://workingwithweb.it/webagency/) — Working With Web.

Progetto Virtual Gate: [virtualgate.workingwithweb.eu](https://virtualgate.workingwithweb.eu/)
