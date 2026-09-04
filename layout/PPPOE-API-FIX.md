# PPPoE API fix

The PPPoE endpoint now explicitly loads:
`library/routeros_api.class.php`

This matches the working Traffic API and fixes:
`Class "RouterosAPI" not found`.

The endpoint continues to return JSON for both success and errors.
