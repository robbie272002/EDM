// Format known monetary fields
if (['total', 'price', 'tax', 'discount'].includes(key)) {
    value = '₱' + parseFloat(value).toFixed(2);
} else if (key === 'id' || key === 'USER ID') {
    // Do not format ID fields with a money sign
    value = value;
} 