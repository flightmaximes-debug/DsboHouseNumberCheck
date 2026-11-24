# Lieferadresse bei Auftragserstellung prüfen

Die Plugin-Aktion prüft die Lieferadresse neuer Standardaufträge aller Herkünfte. Bei einer fehlerhaften Adresse setzt sie den Auftragsstatus auf **5.1** und ergänzt eine interne Auftragsnotiz mit den Fehlergründen. Mehrere Fehler erscheinen gemeinsam in einer Notiz.

> Automatische Lieferadressprüfung
> - Die Hausnummer fehlt im Feld Adresse 2.
> - Address Name 1 (Firma) ist zu lang. Das Feld darf höchstens 40 Zeichen enthalten.

| Feld | Prüfung |
| --- | --- |
| Adresse 1 | Straße in einem eigenen Feld. Zusammengeführte Angaben wie `Musterstraße 12a` fallen in die Prüfung. |
| Adresse 2 | Hausnummer. `12`, `12a` und `12–14` sind gültig. `12 Hinterhaus` wird beanstandet. |
| Adresse 3 | Hier sind Zusätze wie `Hinterhaus` oder `Wohnung 3` möglich. |
| Name 1 | Maximal 40 Zeichen. Bei Privatadressen kann das Firmenfeld leer sein. Mindestens ein Empfänger- oder Firmenname muss vorhanden sein. |
| Ort, Postleitzahl und Land | Ort und Land müssen vorhanden sein. Für Deutschland sind fünf und für Österreich sowie die Schweiz vier PLZ-Ziffern erforderlich. Bei anderen Ländern prüft das Plugin das allgemeine Format einer eingetragenen Postleitzahl. |
| Packstation und Postfiliale | Für deutsche DHL-Abholadressen gehört die dreistellige Stations- oder Filialnummer in Adresse 2. Bei Packstationen ist die Postnummer als Adressoption oder in Adresse 3 erforderlich. |

Die Prüfung arbeitet mit den gespeicherten Adressfeldern. Ob die Anschrift tatsächlich existiert und zustellbar ist, lässt sich nur mit Referenzdaten prüfen. Aufträge ohne zugewiesene Lieferadresse werden übersprungen.

## Bestehenden Flow aktualisieren

Das Plugin-Set mit Branch `main` erneut bereitstellen. Der bestehende Flow mit dem Auslöser **Auftrag erstellt** und der Plugin-Aktion **DSBO: Hausnummer prüfen und bei Fehlen Status 5.1 setzen** verwendet dieselbe Aktion für die erweiterte Prüfung.

Für eine Erstinstallation das Repository unter **Plugins → Git** hinzufügen. Das Plugin im passenden Set installieren und bereitstellen. Im Flow Studio **Ereignisgesteuert → Auftrag erstellt → Auftragstyp Auftrag** auswählen und die Aktion unter **Plugins (Legacy)** hinzufügen. Für alle Herkünfte den Herkunftsfilter frei lassen.

[PlentyONE-Handbuch zu ereignisgesteuerten Flows](https://knowledge.plentyone.com/de-de/manual/main/automatisierung/plentyflow-studio-ereignisgesteuerten-flow-erstellen.html) · [Plugin-Aktionen im Flow Studio](https://knowledge.plentyone.com/de-de/manual/main/automatisierung/plentyflow-aktionen-verwenden.html)
