
1. insert after perfomring the following conditions:
    a hyphen after 1125. i.e
        preg_replace("/1125 /", "1125-", $value);
        preg_replace("/1125 - /", "1125-", $value);
        preg_replace("/1125CHECK FOR NUMBER/", "1125-", $value);
        preg_replace("/1125- /", "1125-", $value);

2. insert open invoice where amount_transaction_currency > 0;

3. for each of the invoice look up bank statement for matching 'purpose_of_use'
    multiple invoice from 'open invoice' can be found in 'bank statement'
    the sum total('amount_transaction_currency') of the invoices from 'open invoice' will match the 'original_amount' in 'bank statement'

export the result
    i.e select trans_date, '13002', purpose_of_use, '', '1.68', original_currency, company_customer, trans_date, '01'
from bank_statement where original_amount = 1.68;

===========

for point 1:
- remove new lines before insert

for point 2:
- check number format for amount

for point 3:
- get rows from 'bank statement' where 'purpose of use' contains mathcing inovice pattern
- create an array of invoices from it
- find rows from 'open invoice' where invoices matches the array of invoice
- sum the rows from 'open invoice'
- check if the sum matches the total from the row in 'bank statement'
- write to the export csv