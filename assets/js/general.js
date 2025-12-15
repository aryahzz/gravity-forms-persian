function convert_digits_to_english(str, remove_chars = true) {
    const map_digits = {
        '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
        '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9'
    };
    // Convert all digits to english
    let result = str.replace(/[۰-۹٠-٩]/g, d => map_digits[d] || d);
    if (remove_chars) { // Only keep english numbers
        result = result.replace(/[^0-9]/g, '');
    }
    return result;
}

(function ($) {
    const pgf_convertable_digits = [
        {selector: '.gfield--type-number input[type="number"]', change_type: true},
        {selector: '.gfield--type-ir_national_id input.ir_national_id'},
        {selector: '.gfield--type-phone input[type="tel"]'},
        {selector: '.gfield--type-sms_verification input.verify_code', change_type: false, remove_chars: false},
        {selector: '.gfield--type-address .address_zip input[type="text"]'},
        {selector: '.gfield--type-product .ginput_container_product_price input[type="text"]'}
    ];

    pgf_convertable_digits.forEach(function (item) {
        let selector = item.selector;
        let change_type = item.change_type;
        let remove_chars = item.remove_chars;

        $(selector).each(function () {
            let input_field = $(this);
            if (change_type) input_field.attr('type', 'text');
            input_field.on('input', function () {
                input_field.val(convert_digits_to_english(input_field.val(), remove_chars));
            });
        });
    });
})(jQuery)