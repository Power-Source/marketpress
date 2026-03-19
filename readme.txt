=== PS MarketPress ===
Contributors: PSOURCE
Tags: E-commerce, ecommerce, storefront, sell, store, shopping, cart, payment gateways, digital downloads, online store
Requires at least: 3.7
Requires PHP: 7.4
Tested up to: 6.8.1
ClassicPress: 2.6.0
Stable tag: 1.0.4

PS MarketPress ist dein leistungsstarker E-Commerce-Marktplatz für ClassicPress und Multisite. 100 % kostenlos, ohne Pflicht-Add-ons.

== Beschreibung ==

PS MarketPress ist eine umfassende E-Commerce-Lösung für ClassicPress und Multisite.
Mit PS MarketPress verkaufst du physische Produkte, digitale Downloads und Varianten in einem zentralen, leistungsstarken Shop-System.

= Multisite im Fokus =

PS MarketPress ist besonders stark im Multisite-Einsatz:
Du kannst ein Netzwerk aus Shops aufbauen, zentrale Vorgaben setzen und trotzdem jede Site flexibel betreiben.
Ideal, wenn du ein Marketplace-Modell wie Etsy/eBay nachbauen oder mehrere Marken-Shops unter einem Dach verwalten willst.

= Komplett kostenlos, trotzdem Premium-Funktionen =

Du brauchst keine zusätzlichen Lizenzen oder kostenpflichtigen Erweiterungen.
PS MarketPress bündelt viele Funktionen, die sonst nur über mehrere separate Plugins verfügbar sind.

= Funktionen = 

Deinen eigenen Online-Shop richtest du mit PS MarketPress schnell und übersichtlich ein. Zu den Funktionen gehören:

* Besonders stark für Multisite-Setups mit mehreren Shops in einem Netzwerk
* Ein vollständiges Paket mit 15 Zahlungsgateways
* Wähle aus 120 verschiedenen Währungen deine Standardwährung
* Vollständig internationalisiert von der WPML-Crew
* Verkaufen Sie reale Objekte oder digitale Downloads
* Einfacher Checkout auf einer Seite
* Einfache Einrichtung mit integriertem Assistenten
* Lege fest, wie oft ein Kunde eine gekaufte Datei herunterladen kann
* Bereit für Steuern und Mehrwertsteuer
* PDF-Rechnungen
* Berechnete Versandmodule (UPS, USPS, Fedex, Abholung im Geschäft)
* Pinterest-, Facebook- und Twitter-Share-Buttons
* Gutscheine, Rabatte und Affiliate-fähig
* Vollständig integriert mit Google Universal Analytics E-Commerce-Tracking
* Unbegrenzte Produktvariationen
* Lagerverfolgung und Benachrichtigungen pro Variation
* Produktlimits pro Bestellung
* AJAX-Warenkorb und Warenkorb-Widget
* Leistungsstarke Shortcodes, die du überall verwenden kannst
* Shortcode-Schaltfläche im visuellen Editor des Beitrags
* Verknüpfe jedes Produkt mit einem externen Link
* Lagerverfolgung, Bestellverwaltung und Benachrichtigungen
* Produkte automatisch ausblenden, wenn sie nicht mehr auf Lager sind
* Ähnliche Produkte anzeigen
* Checkout ohne Registrierung als Benutzer der Website


= Anpassen ohne Programmierkenntnisse = 

PS MarketPress ist so konzipiert, dass es nahtlos mit jedem gut programmierten ClassicPress-Theme funktioniert.

* Shortcodes und integrierte Widgets ermöglichen dir, Elemente überall auf deiner Website anzuzeigen
* Enthält CSS-Stilvorlagen
* Produkt-Thumbnails/Bilder mit Lightbox-Zoom

Oder laden Sie eines unserer Upfront-Themes herunter, die integrierte MarketPress-Stile und einen leistungsstarken Drag-and-Drop-Frontend-Theme-Editor bieten.

= Multisite und BuddyPress =

Baue dir dein eigenes eBay- oder Etsy-ähnliches Netzwerk aus Shops auf und verdiene an Verkäufen im Netzwerk mit.

Egal ob ein einzelner Shop oder ein komplettes E-Commerce-Netzwerk: Mit PS MarketPress bist du dafür sauber aufgestellt.

= Stark im PSOURCE-Ökosystem =

PS MarketPress arbeitet mit vielen weiteren PSOURCE-Plugins zusammen und lässt sich dadurch flexibel erweitern.

Mit dem PSOURCE Manager kannst du noch mehr entdecken, bequem verwalten und neue Erweiterungen schneller einbinden:
https://github.com/Power-Source/ps-update-manager/releases

So bleibt PS MarketPress zuverlässig mit Updates versorgt und entwickelt sich laufend weiter.


== Changelog ==

= 1.0.4 =

* Neu: Statistik-Addon erfasst jetzt auch Gratis-Downloads; jeder Download-Vorgang wird in der Tabelle `wp_mp_download_events` gespeichert (Felder: Order, Produkt, Nutzer, Betrag, `is_free`-Flag, Zeitstempel). Die Statistikübersicht zeigt Gratis-Downloads als zweites Chart-Dataset auf einer eigenen rechten Y-Achse sowie als eigene KPI-Kachel neben dem Gesamtumsatz.
* Security: XSS-Risiko in `basicLightbox.js` geschlossen (CodeQL #3); `elem.innerHTML = html` wurde durch `appendChild(node)` ersetzt. Alle vier Caller (`frontend.js`, `mp-cart.js`, `mp-swiper-init.js`, `shortcode-builder.js`) übergeben jetzt DOM-Nodes statt rohe HTML-Strings – AJAX-Responses werden über `DOMParser` geparst (Scripts werden dabei nicht ausgeführt).
* Fix: Zielländer-Auswahl in den Versand-Einstellungen wiederhergestellt; das SlimSelect-Script-Handle `mp-slim-select` wurde korrekt registriert, und das `advanced_select`-Field rendert jetzt ein natives `<select multiple>`-Element statt eines versteckten Inputs (Rückstand aus dem Select2→SlimSelect-Umbau).
* Fix: Installer-Migrationen für PHP 8 gehärtet: fehlende Legacy-Meta-Keys (`mp_price`, `mp_sale_price`, `mp_is_sale`) werden in `update_214()` nun defensiv geprüft, wodurch Warnungen wie "Undefined array key mp_price" entfallen.
* Fix: Datenbank-Migration für `mp_term_relationships` korrigiert; der Index auf `term_id` wird jetzt mit gültigem Namen erzeugt, wodurch dbDelta-Fehler wie "Incorrect index name ''" vermieden werden.
* Fix: PHP-8-Deprecation in `MP_Taxes::calculate()` behoben; der Parameter `$applied_rates` ist nun optional deklariert und erzeugt keine Warnung mehr für "optional parameter before required parameter".
* Update: Dompdf auf v2.0.8 aktualisiert (war v2.0.4); behebt mehrere PHP-8.4-Deprecations für implizit nullbare Parameter in FrameReflower- und FrameDecorator-Klassen sowie in `Options::__construct()`.
* Update: Sabberworm php-css-parser auf v8.9.0 aktualisiert (war v8.4.0); behebt PHP-8.4-Deprecations in Parser, RuleSet und CSSList/Document.
* Update: phenx/php-font-lib auf 0.5.6 und phenx/php-svg-lib auf 0.5.4 aktualisiert.
* Update: masterminds/html5 auf 2.10.0 aktualisiert.
* Fix: Verbleibende implizit-nullable PHP-8.4-Signaturen in dompdf FrameReflower-Klassen (Block, Inline, Table, TableCell, TableRow, TableRowGroup, Text, Page, ListBullet, NullFrameReflower, Image) und in phenx/php-font-lib direkt gepatcht.
* Fix: Bestell-Metaboxen im Admin erzeugen keine Object-Cache-Notice mehr; `MP_Orders_Admin::add_meta_boxes()` übergibt nun das aktuelle Bestell-Postobjekt korrekt an `MP_Order`, und Cache-Zugriffe in `MP_Order::_get_post()` sind zusätzlich gegen leere Order-IDs abgesichert.
* Verbesserung: PDF-Lieferscheine in allen drei Template-Varianten (`default`, `modern`, `minimalist`) komplett überarbeitet; klarere Kopfbereiche, saubere Adressblöcke, deutlich lesbarere Artikeltabellen und professionelleres Layout.
* Fix: PDF-Lieferscheine verwenden keine extern geladenen Webfonts mehr; stattdessen kommt eine PDF-taugliche Standardschrift zum Einsatz, wodurch Darstellungsprobleme und unerwünschte Dompdf-Font-Artefakte vermieden werden.

= 1.0.3 =

* Fix: Legacy-Datenbank-Upgrade-Trigger für die 1.x-Linie deaktiviert, damit die Meldung "MarketPress requires a database update" bei normalen Plugin-Updates nicht mehr wiederholt erscheint.
* Security: Gast-Bestellstatus wurde gehärtet; nicht eingeloggte Zugriffe auf Bestelldetails erfordern nun die passende Gast-E-Mail-Prüfung statt nur der Bestellnummer.
* Security: Öffentliche AJAX-Prüfungen für bestehende Benutzernamen/E-Mails wurden mit zusätzlicher Nonce-Validierung abgesichert.
* Security: Mehrere Ausgabestellen für Bestell-/Adressdaten und Multisite-Taxonomieausgaben wurden korrekt escaped, um XSS-Risiken zu reduzieren.
* Security: Multisite-Term-Abfrage wurde auf vorbereitete SQL-Parameter umgestellt.
* Performance: Variations-Updatepfade im Admin wurden entlastet (zentraler Rebuild des Variationsnamens, weniger wiederholte Attribut-/Term-Abfragen).
* Performance: Variations-Laden verwendet optimierte ID-basierte Abfragen und effizienteres Caching.
* Performance: Variation-Popup und Varianten-Metaboxen wurden von N+1-Termabfragen auf Bulk-Termauflösung umgestellt.
* Performance: Shortcode-Builder-Produktsuche wurde auf paginierte, begrenzte Ergebnisse umgestellt (statt ungebremster Vollabfrage).
* Performance: Multisite-Tag-Cloud nutzt nun Version-basiertes Caching mit gezielter Invalidierung nach Index-/Term-Änderungen.
* Performance: Multisite-Produktindexierung wurde auf batchweise Verarbeitung umgestellt, um Speicherlast bei großen Netzwerken zu reduzieren.
* Performance: Post-Select-Feld in psource-metaboxes nutzt ein sinnvolles Standardlimit statt unbegrenzter Abfrage.

= 1.0.2 =

* Fix: Die URL-Logik wurde für Produktlinks vereinheitlicht (Netzwerk-Marktplatz, Produktbilder, Excerpt-Links, Social-Buttons, Widgets und Cart-Export nutzen jetzt konsistent die dynamische Produkt-URL).

= 1.0.1 =

* Neu: Konfigurierbare Kaufen-Button-Texte unter Darstellung für Standard-, Download-, Gratis- und Varianten-Zustände.
* Fix: Netzwerk-Marktplatz-Produktlinks zeigen nun wieder auf die korrekten Produktseiten der Subshops.
* Fix: Globale Kategorie- und Tag-Links im Multisite-Marktplatz verwenden nun dynamische Seiten-URLs statt starrer Pfade.
* Fix: Seitenauswahlfelder im Netzwerk-Admin wurden auf die aktuelle Select-Implementierung umgestellt.
* Fix: Multisite- und Store-Settings-Skripte wurden in Stabilität und Syntax korrigiert.
* Fix: Admin-AJAX-Endpoints wurden mit zusätzlichen Nonce- und Capability-Prüfungen abgesichert.
* Fix: Gutschein-AJAX für Anwenden und Entfernen wurde mit Nonce-Validierung abgesichert.
* Fix: Addon- und Setup-Wizard-AJAX wurde weiter gehärtet, u.a. mit Statistik-Capability-Check und Nonce für das Währungs-Preset.
* Fix: psource-metaboxes AJAX-Handler für save_state und fields_save wurden mit Nonce- und Capability-Prüfungen versehen.
* Fix: Die Post-Select-Suche wurde auf eingeloggte Benutzer mit edit_posts-Berechtigung beschränkt.
* Fix: Die Bestellstatus-Änderung über ajax_change_order_status erfordert nun edit_store_orders-Berechtigung.
* Fix: Die Legacy-Terminmanager-Integration wurde an die aktuelle Hook- und Produktspalten-Struktur von MarketPress angepasst.
* Fix: Produktlisten-Spalten für Terminmanager-/Appointments-Produkte rendern wieder korrekt, ohne doppelte Werte oder Fatal Errors.
* Fix: Terminmanager-/Appointments-Produkte blenden in der Produktliste nur noch die Variationsspalte aus; SKU und Preis bleiben sichtbar.
* Fix: Kompatibilitäts-Shims für externe Integrationen wurden wiederhergestellt, u.a. `Marketpress::$version` und `edit_products_custom_columns()`.
* Fix: Die PHP-8-Kompatibilität wurde verbessert, u.a. durch Ersetzen von `wp_get_sites()` und Entfernen von `utf8_encode()`.

= 1.0.0 =

* Release

