
// import Jquery from 'jquery';
// import str from 'core/str';

export const countdown = (sec) => {

    const button = document.querySelector('#sendotp');
    let abc = sec;
    let countdown = null;

    const updateButton = () => {
        var lang = document.documentElement.lang;
        if (lang == 'vi') {
            button.innerHTML = 'Gửi lại ' + abc; // luôn hiển thị "Lựa chọn"
        } else {
            button.innerHTML = 'Resend ' + abc;
        }
        if (abc === 0) {
          clearInterval(countdown);
          abc = sec;
          if (lang == 'vi'){
            button.innerHTML = 'Nhận OTP';
          } else {
            button.innerHTML = 'Send OTP';
          }
          button.disabled = false;
          return;
        }
        abc--;
    };

    // button.onclick = () => {
        button.disabled = true;
        updateButton();
        countdown = setInterval(function() {
          updateButton();
        }, 1000);
    // };
};