<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Scanner QR Code</title>
  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>
<body>
  <h2>Scanner un QR Code Étudiant</h2>
  <div id="reader" style="width: 400px;"></div>
  <div id="resultat"></div>

  <script>
    function enregistrerPresence(data) {
      fetch('traiter_qrcode.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ qrdata: data })
      })
      .then(res => res.text())
      .then(msg => {
        document.getElementById("resultat").innerHTML = "<p>" + msg + "</p>";
      })
      .catch(err => {
        console.error("Erreur :", err);
      });
    }

    function onScanSuccess(decodedText, decodedResult) {
      html5QrcodeScanner.clear();
      enregistrerPresence(decodedText);
    }

    let html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 });
    html5QrcodeScanner.render(onScanSuccess);
  </script>
</body>
</html>
