<!-- flutterwave-payment.html -->

<!DOCTYPE html>

<html>

<head>

  <title>Flutterwave Payment</title>

  <script src="https://checkout.flutterwave.com/v3.js"></script>

  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

</head>

<body>

<script>

  function getParam(key) {

    const urlParams = new URLSearchParams(window.location.search);

    return urlParams.get(key);

  }

  function makePayment() {

    const amount = getParam("amount") || 1000;

    const order_id = getParam("order_id") || "default_order";

        const email = getParam("email") || "test@example.com"; // ✅ Get email passed from app

    FlutterwaveCheckout({

      public_key: "FLWPUBK-e9819fa1b8d23a17f0b4bdc31f72aa59-X",

      // public_key: "FLWPUBK_TEST-dd1514f7562b1d623c4e63fb58b6aedb-X",

      tx_ref: "txref_" + Date.now(),

      amount: parseFloat(amount),

      currency: "NGN",

      payment_options: "card,ussd",

      customer: {

        email: email,

        name:email

      },

      callback: function (response) {

        console.log("🔍 Flutterwave callback:", response);

        // ✅ More reliable success detection

        const isSuccess =

          response.status === "successful" ||

          response.status === "completed" ||

          response.charge_response_code === "00";

        const message = {

          event: isSuccess ? "success" : "failed",

          data: response

        };

        window.ReactNativeWebView.postMessage(JSON.stringify(message));

      },

      onclose: function () {

        window.ReactNativeWebView.postMessage(JSON.stringify({ event: "closed" }));

      },

      customizations: {

        // title: "My App Payment",

        description: `Order ID: ${order_id}`,


      }

    });

  }

  window.onload = makePayment;

</script>

</body>

</html>
