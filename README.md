# Hausnummer bei Auftragserstellung prüfen

Die Plugin-Aktion prüft die Lieferadresse neuer Standardaufträge aller Herkünfte. Fehlt dort die Hausnummer, setzt die Aktion den Auftragsstatus auf **5.1**. Sie berücksichtigt getrennte Felder und zusammengeführte Straßenzeilen wie `Musterstraße 12a` und `12 High Street`. `Packstation` mit Stationsnummer im zweiten Feld gilt als vollständig. Aufträge ohne zugewiesene Lieferadresse werden übersprungen.

## Einrichtung in PlentyONE

1. Den Inhalt dieses Ordners mit `plugin.json` auf oberster Ebene in ein Git-Repository legen. Das Repository unter **Plugins → Git** hinzufügen. Das Plugin in den Plugin-Sets der betroffenen Mandanten installieren und bereitstellen.
2. Unter **Automatisierung → Flow Studio** einen neuen Flow **Hausnummer bei Auftragserstellung prüfen** anlegen. **Ereignisgesteuert → Auftrag erstellt → Auftragstyp Auftrag** wählen. Den Herkunftsfilter leer lassen.
3. Unter **Aktionen → Plugins (Legacy)** die Aktion **DSBO: Hausnummer prüfen und bei Fehlen Status 5.1 setzen** hinzufügen. Die Aktion ändert den Status direkt.
4. Vor dem Einsatz im laufenden Versand mit neuen Testaufträgen prüfen, dass `Musterstraße` mit leerem Hausnummernfeld auf **5.1** wechselt und eine vollständige Adresse ihren Status behält. Prüfen, ob nachgelagerte Versand- oder Zahlungsabläufe den Status **5.1** verarbeiten oder überschreiben.

PlentyONE bezeichnet **5.1** standardmäßig als **Abwicklung extern**. Der Name und die Verwendung im eigenen System können abweichen. Die Prüfung wertet die im Auftrag gespeicherten Felder aus. Bei zusammengeführten Straßenzeilen erkennt sie typische nummerierte Adressen am Anfang oder Ende der Zeile anhand ihres Formats.

[PlentyONE-Handbuch zu ereignisgesteuerten Flows](https://knowledge.plentyone.com/de-de/manual/main/automatisierung/plentyflow-studio-ereignisgesteuerten-flow-erstellen.html) · [Plugin-Aktionen im Flow Studio](https://knowledge.plentyone.com/de-de/manual/main/automatisierung/plentyflow-aktionen-verwenden.html)
