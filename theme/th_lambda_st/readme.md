# Chức năng
Custom theme named th_lambda_st.
inherit setting from lambda and child of lambda theme

Sửa đổi layout của dạng bài quiz:

## Thao tác
Khi thêm đoạn html dưới đây vào câu hỏi đầu tiên trong cùng một page

```html
<span id="th_mycustomwidth" value="3"></span>

```

Giao diện bài Quiz sẽ chia thành 2 cột    
- Tổng độ rộng của là 12
- Như trong ví dụ ```value=3```, độ rộng của cột thứ nhất là 3, cột thứ 2 là 12 - 3 = 9
