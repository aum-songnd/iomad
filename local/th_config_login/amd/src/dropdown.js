import $ from 'jquery';

function set_cookies(name) {
  var now = new Date();
  var time = now.getTime();
  var expireTime = time + 604800 * 60;
  now.setTime(expireTime);
  document.cookie = 'option='+name+';expires='+now.toUTCString()+';path=/';
}

function getCookie(name) {
  const decodedCookies = decodeURIComponent(document.cookie);
  const cookies = decodedCookies.split(';');
  name = name + "=";
  for (let i = 0; i < cookies.length; i++) {
      let cookie = cookies[i].trim();
      if (cookie.indexOf(name) === 0) {
          return cookie.substring(name.length);
      }
  }
  return null; // Trả về null nếu không tìm thấy
}

const select = document.getElementById("th123");

// Tạo cấu trúc dropdown bằng JavaScript từ select ban đầu
const wrapper = document.createElement('div');
wrapper.className = 'custom-dropdown';

const display = document.createElement('a');
display.className = 'dropdown-display';
display.style.cursor = 'pointer';
var lang = document.documentElement.lang;
if (lang == 'vi') {
  display.innerHTML = 'Chọn phương thức xác minh khác &#9662;'; // luôn hiển thị "Lựa chọn"
} else {
  display.innerHTML = 'Choose other authentication methods &#9662;';
}

const list = document.createElement('div');
list.className = 'dropdown-list';

if (select !== null) {
  // Tạo các option từ select gốc
Array.from(select.options).forEach(option => {
  if (option.value) { // bỏ qua option rỗng ban đầu
    const item = document.createElement('div');
    item.textContent = option.textContent;
    item.dataset.value = option.value;
    if (option.value == getCookie('option')) {
      item.style.display = 'none';
    }
    list.appendChild(item);

    item.addEventListener('click', function(){
      select.value = this.dataset.value; // chỉ đổi value select, KHÔNG thay đổi text hiển thị
      list.style.display = 'none';

      var value = select.value;
      // set_cookies(value);

      if (value == 'cloudflare') {
          $('div.custom-dropdown div.dropdown-list div[data-value="cloudflare"]').css("display", "none");
          $('div.custom-dropdown div.dropdown-list div[data-value="email"]').css("display", "block");
          $('div.custom-dropdown div.dropdown-list div[data-value="recaptcha"]').css("display", "block");
          $('#fitem_id_th_recaptcha_element').css("display", "none");
          $('#fitem_id_th_recaptcha_element').prop('disabled', true);
          $('#fitem_id_email2').css("display", "none");
          $('#fitem_id_email2').prop('disabled', true);
          $('#fgroup_id_button_email').css("display", "none");
          $('#fgroup_id_button_email').prop('disabled', true);
          $('.login-form-recaptcha').css("display", "none");
          $('.login-form-recaptcha').prop('disabled', true);
          $('.login-form-email').css("display", "none");
          $('.login-form-email').prop('disabled', true);
          $('#sendotp').css("display", "none");
          $('#sendotp').prop('disabled', true);
          $('#otp').css("display", "none");
          $('#otp').prop('disabled', true);
          $('#fitem_id_sendotp').css("display", "none");
          $('#fitem_id_sendotp').prop('disabled', true);
          $('#fitem_id_otp').css("display", "none");
          $('#fitem_id_otp').prop('disabled', true);

          $('.login-form-cloudflare').css("display", "block");
          $('.login-form-cloudflare').prop('disabled', false);
          $('#fitem_id_cloudflare_element').css("display", "block");
          $('#fitem_id_cloudflare_element').prop('disabled', false);

          if ($('#id_submitbutton')) {
              $('#id_submitbutton').disabled = false;
              //$('#id_submitbutton').focus();
          }
          if ($('#loginbtn')) {
              $('#loginbtn').disabled = false;
              //$('#loginbtn').focus();
          }
      } else if (value == 'recaptcha') {
          $('div.custom-dropdown div.dropdown-list div[data-value="recaptcha"]').css("display", "none");
          $('div.custom-dropdown div.dropdown-list div[data-value="email"]').css("display", "block");
          $('div.custom-dropdown div.dropdown-list div[data-value="cloudflare"]').css("display", "block");
          $('#fitem_id_email2').css("display", "none");
          $('#fitem_id_email2').prop('disabled', true);
          $('#fgroup_id_button_email').css("display", "none");
          $('#fgroup_id_button_email').prop('disabled', true);
          $('#fitem_id_cloudflare_element').css("display", "none");
          $('#fitem_id_cloudflare_element').prop('disabled', true);
          $('.login-form-cloudflare').css("display", "none");
          $('.login-form-cloudflare').prop('disabled', true);
          $('.login-form-email').css("display", "none");
          $('.login-form-email').prop('disabled', true);
          $('#sendotp').css("display", "none");
          $('#sendotp').prop('disabled', true);
          $('#otp').css("display", "none");
          $('#otp').prop('disabled', true);
          $('#fitem_id_sendotp').css("display", "none");
          $('#fitem_id_sendotp').prop('disabled', true);
          $('#fitem_id_otp').css("display", "none");
          $('#fitem_id_otp').prop('disabled', true);

          $('.login-form-recaptcha').css("display", "block");
          $('.login-form-recaptcha').css("disabled", false);
          $('#fitem_id_th_recaptcha_element').css("display", "block");
          $('#fitem_id_th_recaptcha_element').prop('disabled', false);
          if ($('#id_submitbutton')) {
              $('#id_submitbutton').disabled = false;
              //$('#id_submitbutton').focus();
          }
          if ($('#loginbtn')) {
              $('#loginbtn').disabled = false;
              //$('#loginbtn').focus();
          }
      } else if (value == 'email') {
          $('div.custom-dropdown div.dropdown-list div[data-value="email"]').css("display", "none");
          $('div.custom-dropdown div.dropdown-list div[data-value="recaptcha"]').css("display", "block");
          $('div.custom-dropdown div.dropdown-list div[data-value="cloudflare"]').css("display", "block");
          $('#fitem_id_th_recaptcha_element').css("display", "none");
          $('#fitem_id_th_recaptcha_element').prop('disabled', true);
          $('#fitem_id_cloudflare_element').css("display", "none");
          $('#fitem_id_cloudflare_element').prop('disabled', true);
          $('.login-form-cloudflare').css("display", "none");
          $('.login-form-cloudflare').prop('disabled', true);
          $('.login-form-recaptcha').css("display", "none");
          $('.login-form-recaptcha').prop('disabled', true);

          $('.login-form-email').css("display", "block");
          $('.login-form-email').css("disabled", false);

          // if ($('#sendotp').is(':disabled')) {
          //   $('#sendotp').css("display", "block");
          // } else {
            $('#sendotp').css("display", "block");
            $('#sendotp').prop('disabled', false);
          // }
          $('#fitem_id_email2').css("display", "block");
          $('#fitem_id_email2').prop('disabled', false);
          $('#fgroup_id_button_email').css("display", "block");
          $('#fgroup_id_button_email').prop('disabled', false);
          $('#fitem_id_sendotp').css("display", "block");
          $('#fitem_id_sendotp').prop('disabled', false);
          $('#loginbtn').prop("disabled", false);
          $('#id_submitbutton').prop("disabled", false);
      } else {

      }
    });
  }
});
// Append các thành phần vào wrapper và thay vào DOM
wrapper.appendChild(display);
wrapper.appendChild(list);
select.parentNode.insertBefore(wrapper, select);
list.style.maxWidth = "500px";
// Click hiển thị dropdown
display.addEventListener('click', function(e){
  e.stopPropagation();
  list.style.display = list.style.display === 'block' ? 'none' : 'block';
});

// Click ngoài dropdown thì đóng lại
document.addEventListener('click', function(){
  list.style.display = 'none';
});
}
