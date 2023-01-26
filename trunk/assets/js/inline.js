var checkoutData = null;

function getUrlBase(environment) {
  return environment == "production" ? "https://api.bitnob.co/" : "https://sandboxapi.bitnob.co/";
}

function buildUrl(urlBase, data){
  return  url = urlBase + 'api/v1/checkout-inline?publicKey=' +
  data.publicKey +
  "&callbackUrl=" +
  data.callbackUrl +
  "&successUrl=" +
  data.successUrl +
  "&notificationEmail=" +
  data.notificationEmail +
  "&customerEmail=" +
  data.customerEmail +
  "&satoshis=" +
  data.satoshis +
  "&reference=" +
  data.reference;
}

function buildFetchData({ method, data }) {
var fetchData = {
  method,
  cache: "no-cache",
  credentials: "same-origin",
  headers: {
    "Content-Type": "application/json",
  },
  redirect: "follow",
  referrerPolicy: "no-referrer",
};
if (data) fetchData.body = JSON.stringify(data);
return fetchData;
}

function convertToSatoshis(amount, currency, environment) {
  conversion = `${currency.toUpperCase()}_SAT`;

  let url = getUrlBase(environment) + "api/v1/rates/convert-currency/";

  data = { conversion: conversion, amount: amount };
  conversion = `${currency.toUpperCase()}_SAT`;
  return fetch(url, buildFetchData({ data, method: "POST" }));
}

function createCheckout(data, environment) {
  createLoaderIframe();

  fetch(buildUrl(getUrlBase(environment), data), buildFetchData({method: "GET" })).then( (res) => 
    res.json()
  ).then((response) =>
    setIframe(response.data, environment)
  )
}

function initializePayment(data, environment) {
  convertToSatoshis(data.amount, data.currency, environment).then( (res) => 
    res.json()
  ).then( (response) => {
    data.satoshis = response.data;
    createCheckout(data, environment);
});
}

function removeIframe(id) {
  var iframe = document.getElementById(id);
  document.body.removeChild(iframe);
}

function createLoaderIframe() {
  iframe = document.createElement("iframe");
  iframe.setAttribute("allowtransparency", "true"),
    iframe.setAttribute("id", "loader");
  iframe.src =
    "data:text/html;charset=utf-8, <body style='background-color:rgba(0, 0, 0, 0.8);'>" +
    "<div style='position: absolute;display: flex;flex-direction: column;align-items: center;justify-content: center;height: 100%;width: 100%;z-index: 1000; text-align:center'>" +
    "<img src='https://res.cloudinary.com/gabbyprecious/image/upload/v1651003230/haxqforaozaqsl0ksber.gif'width='100px' alt='Logo'/>" +
    "</div></body>";
  iframe.style.cssText =
    "z-index: 999999999999999;background: transparent;border: 0px none transparent;overflow-x: hidden;overflow-y: hidden;margin: 0;padding: 0;-webkit-tap-highlight-color: transparent;-webkit-touch-callout: none;position: fixed;left: 0;top: 0;width: 100%;height: 100%;";
  document.body.appendChild(iframe);
}

function setIframe(checkout, environment) {
  checkoutData = checkout;
  iframe = document.createElement("iframe");
  if (environment == "sandbox") {
    url = "https://sandbox-popup.bitnob.co/" + checkoutData.id;
  }
  if (environment == "production") {
    url = "https://popup.bitnob.co/" + checkoutData.id;
  }
  iframe.src = url;
  iframe.setAttribute("allowtransparency", "true"),
    iframe.setAttribute("id", "checkout"),
    iframe.setAttribute("sandbox", "allow-scripts allow-same-origin");
  iframe.style.cssText =
    "z-index: 999999999999999;background: transparent;border: 0px none transparent;overflow-x: hidden;overflow-y: hidden;margin: 0;padding: 0;-webkit-tap-highlight-color: transparent;-webkit-touch-callout: none;position: fixed;left: 0;top: 0;width: 100%;height: 100%;";
  document.body.appendChild(iframe);
}

function callSuccessUrl() {
  if (checkoutData.successUrl) {
    url =
      checkoutData.successUrl +
      "?checkoutId=" +
      checkoutData.id +
      "&reference=" +
      checkoutData.reference +
      "&status=" +
      "paid";
    window.location.replace(url);
  }
}

window.onmessage = function (e) {
  if (e.data == "mounting-loader") {
    removeIframe("loader");
  }
  if (e.data == "popup-closed") {
    removeIframe("checkout");
  }
  if (e.data == "paid") {
    setTimeout(() => {
      window.callSuccessUrl();
    }, 10000);
  }
};

window.callSuccessUrl = callSuccessUrl;

window.createCheckout = createCheckout;

window.initializePayment = initializePayment;

window.checkoutData = checkoutData;