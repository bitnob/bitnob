function setReference() {
    let text = "";
    let possible =
        "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

    for (let i = 0; i < 10; i++)
        text += possible.charAt(Math.floor(Math.random() * possible.length));

    return text;
}
function pay(publicKey, amount, customerEmail, reference) {
    var data = {
        publicKey: publicKey,
        amount: amount,
        customerEmail: customerEmail,
        notificationEmail: document.getElementById('admin_email').value,
        description: document.getElementById('description').value,
        currency: document.getElementById('currency').value,
        reference: reference,
        callbackUrl: document.getElementById('callbackUrl').value,
        successUrl: document.getElementById('successUrl').value,

    };
    window.initializePayment(data, document.getElementById("environment").value);
}

function getFormData(e) {
    e.preventDefault();
    var email = document.getElementById('email').value
    var amount = document.getElementById('amount').value
    var reference = setReference();
    pay(document.getElementById('publicKey').value,
        amount,email,reference
    );
}
window.addEventListener('load', (event) => {
    if(document.getElementById('callbackUrl') !== null){
        getFormData(event);
    }
});