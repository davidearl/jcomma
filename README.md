# ![logo](https://jcomma.savesnine.info/logo.svg) jcomma: help

jcomma converts CSV files to other formats and sanitizes them so that they are not subject to the vagaries of the generator of the CSV. **Live version at [https://jcomma.savesnine.info](https://jcomma.savesnine.info)**

For example, banks frequently generate CSVs with:
* interleaved headers every few entries,
* more than one header line,
* currency symbols where you just want a number,
* dates in unsuitable or ambiguous formats,
* parentheses or trailing minus signs as negatives where ordinary mortals would put a normal negative,
* positive values (e.g. deposits) in one column and negatives in another (e.g. withdrawals) where you just want a single number which is positive or negative.


Furthermore, CSV files don't have any means to indicates what
character set encoding they use, and very often Excel - or the
person who used Excel to send it to you - produces one (typically
Windows) and your consumer needs another (often UTF-8). Particularly
a problem with things like &pound; signs.

## Usage

See also [installation](#hinstallation).

(#husewebapp)
### As a web app

Fill in the form and click **Do It!**. Each reload of the page remembers the settings except for the CSV file to upload, and you can also save and restore settings from file.

(#huseapi)
### Via API

Do a multipart-encoded POST to /jcomma.php, providing the CSV file as 'csv' and the specification as 'spec', a JSON string encoding all the options, as shown below, in a single POST parameter. For example:

        curl -F "spec=@spec.json" -F "csv=@my.csv" "https://jcomma.savesnine.info/jcomma.php"

You can use the web app to make a spec file, save it, and then use that programatically; or make one on the fly.

(#huselibrary)
### As a library

To use as a library include jcomma.class.php in your PHP application, and use like this:

    $j = new jcomma($pathtocsv, $specobject /* not JSON: already decoded */);
    $errors = $j->validate(); // produces array of error message strings, or empty arry iof spec is OK
    if (empty($errors)) {
        $result = $j->convert(); // produces array of objects
        $jcomma->output($result, $filename, $cl); /* optional: if you want to actually emit a file or string */
    }

$spec->outputTo can be set to 'string' (unlike when used via the API), when it
is converted to the output format but returned as a
string rather than to stdout. $cl=TRUE is optional, and omits all headers

(#huseshell)
### In a shell command line

Like this, for example:

    php jcomma.php -s spec.json my.csv

The result is written to standard output. spec.json is the file containing a json representation of the options, as shown below. If the input csv path is '-' or omitted, it is read from standard input, so the command can be used in a pipe:

    echo my.csv | php jcomma.php -s spec.json > my.xml

(#hloadsettings)
## Load settings from file

You can load all the settings previously saved with the link at the bottom of the page (or prepared elswhere) by opening the JSON file here onto the **choose file** button here. In Chrome you can just drop the file onto the button.

(#hresetsettings)
## Reset settings

Clicking this just resets the page to the simplest possible settings for you to then exand on (it may be needed since the page remembers each change).

## Output settings


(#hcomment)
### Comment

This is just saved with the settings so when you look in the file there is something to tell you what it is for. It's not saved with the output.

(#houtputto)
### Output to

Choose whether to save the output to a downloaded file or to display it in a browser tab. Note that csv and xlsx files are always downloaded (because browsers don't know what to do with them).

This corresponds to the `"outputTo": "wherever"` [in the spec](#hspec), and when used as the library, a third 'string' is available to retrieve the result as a string.

(#houtputformat)
### Output format

jcomma can write its result in a variety of formats (`"outputStyle": "json"` for example [in the spec](#hspec). Some of these have additional options:

* **json**: creates a JSON array of objects, one for each line, or group of lines, in the input CSV. (Note: JSON is encoded as UTF-8 by definition).
    * ***pretty print*** (`"outputStyle": "pretty"` [in the spec](#hspec)): lays out the JSON output in an easier-to-read layout rather than all in one line
    * ***bulk data for elasticsearch***: (`"outputBulkElastic": true` [in the spec](#hspec)): instead of an array, creates one line for each record, interleaved with JSON objects to control elasticsearch for creation of elasticsearch documents using its [bulk api](https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-bulk.html). The type of each record is filled in from the type name field if given (`"outputName": "name"` [in the spec](#hspec)) or the name of the input file (less suffix) if not. (note: pretty print is ignored in this case)
* **csv**: a transformation of the input CSV. Each record generated forms a row and each field generated a column cell in that row.
    * ***output encoding*** (`"outputEncoding": "whatever"` [in the spec](#hspec)): the character encoding of the output CSV.
    * ***include header row*** (`"outputHeaderRow": true` [in the spec](#hspec)): include a header row comprising the field names given below
* **xlsx**: Microsoft's Excel 2007 spreadsheet format. This is better than CSV in that the character encoding is well defined, and the types of the data cells can be set explicitly. This uses [PHP_XLSXWriter](https://github.com/mk-j/PHP_XLSXWriter) (*thank you mk-j; MIT license*) to make the file.
    * ***include header row*** (`"outputHeaderRow": true` [in the spec](#hspec)): include a header row comprising the field names given below
* **html**: A HTML table with suitable surrounding html syntax so it forms a complete html page
    * ***include header row*** (`"outputHeaderRow": true` [in the spec](#hspec)): include a header row (in a HTML *thead* element) comprising the field names given below
* **xml**: An XML file, in one of two alternative layouts.
    * ***values as elements rather than attributes*** (`"outputXMLElements": true` [in the spec](#hspec)): When checked, each field generated is output as the content of an element named according to the field name given, those elements within a container element for each record. When not checked, each field is output as an attribute of the container record, the attribute name derived from the field name.
    * ***element name*** (`"outputName": "name"` [in the spec](#hspec)): the name of the record elements. All these elements are wrapped in a containing element named by appending an 's' to this name. So if the name entered is "person", we'd get an XML file like this:

            <persons>
                <person a="..." b="..." ... />
                <person a="..." b="..." ... />
            </persons>

* **qif**: Quicken Interchange Format. Arbitrary field names are not allowed in a QIF file, so this also changes the field name box below to a menu of those permitted in QIF files.
    * ***Transaction types*** (`"outputQIFType": "whatever"` [in the spec](#hspec)): A QIF file has a header which identifies the kind of content, such as 'Bank' for bank statement lines

## Input settings

(#hcsv)
### CSV file

Select the CSV file to process. In Chrome you can just drag and drop the file onto the Choose File button. Everything that follows then describes how that CSV file should be interpreted to convert it to output records (which are then formatted according to the chosen file format and options).

(#hencoding)
### Input CSV encoding

CSV files are completely ambiguous when it comes to characters that aren't represented in the basic ASCII character set, such as accented characters, currency symbols and exotic punctuation, not to mention whole languages like Japanese and Hebrew. While Excel does have an option to choose the character set when it is saved, most people do not know about it and are completely unaware of the consequences, namely that a consumer of the CSV may not see currency symbols correctly. Typically Excel exports CSVs using a special Windows character set, while Google Sheets does so using the standard international character set called UTF-8.

This option (`"encoding": "whatever"` [in the spec](#hspec)) lets you explicitly state which character set to expect in the CSV (we will try to detect this when set to auto, but it is not always possible to be correct.

(#hheaderrows)
### Header rows

Many CSV files have a header row labelling the columns. Some CSV files have additional header rows where people put arbitrary data (for example, a bank statement CSV might use one line for the account number, and another for the sort code).

Specify the number of header rows in the CSV here (`"headerRows": 1` for example [in the spec](#hspec)). Zero means there is no header row at all, while 1 is common.

If header rows is 1 or more, the last of them is assumed to contain column headings, and these can be used to identify columns instead or as well as column letters. This can make the settings easier to follow. Column headings and letters are not treated as case-sensitive. Also note that conventionally the 27th and subsequent columns are "AA", "AB", "AC" etc, and the 53rd "BA", "BB", "BC", and so on, as in Excel.

(#hrowcount)
### Each record formed from ... rows

When the CSV file is processed, typically each row is used to create one (or sometimes more) output records. However, sometimes a record might be formed from a group of rows if the data has been spread over more than one row.

This number (`"rowCount": 1` for example [in the spec](#hspec)) says how many rows to consume (after any rows that are ignored below have been discarded) to construct each record (or group of records). 1 is typical.

(#hignorerows)
### Ignore rows...

People will often put random rows in the middle of CSVs which makes them particularly hard to process in an orderly manner. For example, banks often repeat their a header row every "page", whatever that might mean.

This setting (`"ignoreRows": [ ... ]` [in the spec](#hspec)) lets you ignore such rows based on one or more criteria. Add a new criterion by pressing the + button, remove an existing one with X, and drag &#x2195; to change the order. The row is ignored if any one of the criteria is satisfied.

You can ignore rows based on the value of a cell in a specified column in the incoming CSV (provide its name, either the column letter or column heading), or based on the value of one of the fields computed from the incoming CSV (provide its name to match one you specify below).

See [conditions](#hconditions) for details about the conditions (the same set of conditions is used in several different places).

Note that rows are not ignored in this way when extracting any header row(s) (where exactly the number given are consumed from the CSV).

(#hrecords)
## Output records

Typically you'll produce one output record for each input row (that isn't ignored according to [ignore rows](#hignorerows) or group of however many rows given by ['Each record formed from...'](#hrowcount). However, occasionally it may be helpful to make more than one record for each row. Each such record would appear consecutively in the output, e.g. as an object in a JSON array or an extra row in an output CSV.

For example, you might produce two records from a PayPal transaction where you want to represent a payment as one record (a credit), and the paypal fee (which paypal puts in the same row in its exports) as another (a debit: a bank charge).

Therefore, the + here lets you add one or more specifications of records to be produced, though you'll often only need one. ([in the spec](#hspec), *"records": [ ... ]* reflects this). Click X to remove one, and drag &#x2195; to change the order

Each record then comprises a set of fields, and a set of conditions based on those fields when the record will be discarded.

(#hfields)
## Fields of output record

Each field (`"fields": [ ... ]` [in the spec](#hspec)) is given a name, one or more columns and bits of verbatim text from which its value is initially composed, and a list of options applied in turn which can transform, omit or reject the value.

Add a new field with +, delete an existing one with X, and drag &#x2195; to change the order

Fields are delivered in the order they are specified in the output records.

(#hname)
### Field name

Field names (`"name": "whatever"` [in the spec](#hspec)) is used in the output, for example as object member names in JSON, as element names in XML or column headings in CSV, HTML and XLSX.

If names contain periods, then subordinate objects are constructed for those formats which can support hierarchical output. For example, we might have output fields called "salutation", "address.city" and "address.postcode". In JSON output these would appear as:

    [
        {
            "salutation": "Miss Doe",
            "address": {
                "city": "Lerwick",
                "postcode": "ZE1 1AA"
            }
        },
        ...
    ]

and in XML (with elements named "person") as, for example:

    <persons>
        <person>
            <salutation>Miss Doe</salutation>
            <address>
                <city>Lerwick</city>
                <postcode>ZE1 1AA</postcode>
            </address>
        </person>
    </persons>

In table formats (HTML, CSV, XLSX), the values just appear as consecutive columns headed (when requested) by the dotted name.

You can usefully have more than one field with the same name (usually consecutive), providing you set options to omit each in opposite circumstances. For example, say you have debit and credit columns in the original CSV but require a single simple number, positive for credit and negative for debit. So you would make one field from credit and the same field from debit, each of which has the option to omit if blank, and convert to a number, while debit also includes the option to negate when converting to a number.

(#hcomprising)
### Field concatenated from

You can concatenate several columns, interleaved with verbatim separators, to make one output field, before applying any options. For example, some banks provide several fields which one might usefully put into a single Description field.

Where there is more than one input row ([rowCount](#hrowcount) is greater than 1), you'll need to say from which of those rows the CSV cell is obtained (this also allows you to concatenate several values vertically from the same column), by giving the row offset (0 for the first row, 1 for the second in each group of rows, and  so on).

Click + to include a new column or text, X to remove one, and drag &#x2195; to change the order

(#hoptions)
### Field options

After a field value is derived by concatenation from various cells in the CSV, that value can be transformed in a variety of ways using field options. More than one option can be applied, in the order they are given. Click + to add a new option, X to delete an existing one, and drag &#x2195; to change the order.

Available options are as follows:

* ***ignore currency symbols...*** (`"item": "ignoreCurrency", "currencies": "&pound;..."` [in the spec](#hspec)): Any currency symbols (or indeed any other single character) that appears in the list provided are removed from the field value, wherever they appear in the string. Examples: if the currency list was just "&pound;", and the value so far is "£123.45", the result would be "123.45". However, if the list were "&deg;" (the degree symbol) and the value was "90&deg;" this option can also be used to remove that, yielding "90".

* ***treat '(1.23)' or '1.23-' as negative...*** (`"item": "bookkeepersNegative"` [in the spec](#hspec)): accounts sometimes represent negative numbers by enclosing them in parentheses (which makes them more obvious, but hard to process), or even sometimes with a trailing instead of leading minus sign. Convert these into proper negatives with this option. Note that you are still left with a string at the end of this and should probably also convert it to a number (below). Examples: "(123.45)" &rarr; "-123.45"; "123.45-" &rarr; "-123.45".

* ***trim surrounding white space*** (`"item": "trim"` [in the spec](#hspec)): remove all preceding and trailing spaces, newlines, line-feeds and tabs from the value, any number in any mixture. Example: "&nbsp;&nbsp;123.45&nbsp;&nbsp;" &rarr; "123.45"

* ***replace all occurences of string*** (`"item": "replaceString", "matches": "string", "output": "replacement"` [in the spec](#hspec)): Replaces all occurences in value of the guiven string with its replacement. For example, if value is "the cats scattered" match is "cat" and  replacement is "dog", the result is "the dogs sdogtered"!

* ***replace using regular expression*** (`"item": "transform", "matches": "regexp", "output": "replacement"` [in the spec](#hspec)): If the field matches the provided [regular expression](#hregularexpression) then it is replaced by the provided replacement. Fragments matched can be substituted using $1, $2 etc for parenthesised elements in the regular expression. Example: if the value is "the cat sat on the mat", the regular expression "/ c?t /" and the replacement " dog ", we end up with "the dog sat on the mat".

* ***output as number*** (`"item": "convertToNumber", "errorOnType": true, "negate": true` [in the spec](#hspec)): Converts the value to a number in the output, so that you can do artihmetic on it in the output for example (it is also then amenable to the greater and less options in subsequent tests here). If the string value cannot be converted ("12a" for example), then the whole CSV conversion is stopped with an appropriate error message if the ***stop on conversion*** box is checked, or output as 0 (zero) otherwise. If the ***negate after conversion*** box is checked, the result is the negative of the converted number. For example the debit column of a bank statement may need to produce the negative of the cell contents if being combined with a credit column.

* ***output as ISO date*** (`"item": "convertToDate", "errorOnType": true, "dateFormatUS": true, "dateFormatTime": true` [in the spec](#hspec)): Converts the value to standardised date whatever its original form. The output format is specified by international standard [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601) and looks like "2016-12-02" or "2016-12-02T13:36:45+00:00" if the time is included; many systems recognise this format which is both easily sortable and unambiguous. Conversion uses the Linux [strtotime](http://php.net/manual/en/function.strtotime.php) function, so it understands a wide variety of possible values. However, European and US dates written using slashes, such as "3/4/2016" are ambiguous (European is d/m/y hence 3 April, while US is m/d/y, hence March 4), so the ***US dates*** check box allows you to indicate which the CSV contains (US if on). (It does this by replacing slashes with dashes in the European case before presenting to strtotime.). If the value is not a comprehensible date, then either the whole CSV conversion is stopped if the ***stop on conversion*** box is checked, or output as an empty string otherwise.

* ***output as custom date*** (`"item": "convertToCustomDate", "errorOnType": true, "dateFormatUS": true, "dateFormatStyle": "j M Y"` [in the spec](#hspec)): Similar to ISO date, but you can specify the output style yourself (including time parts). This uses the [PHP date function](http://php.net/manual/en/function.date.php) in which letters in the date style are replaced by parts of the date or time. For example "M j, Y" produces dates like "Dec 1, 2016" because M means the abbreviated month name, j means the day without leading zeros and Y means the four digit year. Many other variants are possible - see [date](http://php.net/manual/en/function.date.php).

* ***omit field if...*** (`"item": "omitIf", "condition": "...", ...` [in the spec](#hspec)): Having transformed the field by whatever other methods, the result can be discarded completely if the [condition](#hcondition) selected here is satisifed. Omiting a field is potentially useful in JSON and XML formats, but in tabular formats (CSV, HTML, XLSX) this would result in columns shifting left by one, so it would be better to transform (above) to an empty string value instead.

* ***stop with error if value...*** (`"item": "errorOnValue", "condition": "...", ...` [in the spec](#hspec)): Having transformed the field by whatever other methods, the whole conversion process can be terminated if the [condition](#hcondition) selected here is satisifed: for example if an unexpected value is encountered.

## (#unless)Don't output record...

Having calculated all the fields for a record, the values computed can be used to determine that their record should not be included in the output at all if any of the [conditions](#hconditions) given here are satisfied. Press + to add a new condition, X to remove an existing one, and drag &#x2195; to change the order.

(#hsavesettings)
## Save settings to file

All the settings described in the form are saved as a jcomma specification, a JSON file, to a local file. Note that changes are saved on every change so if the page is reloaded, changes are not lost.

As well as [restoring them](#hloadsettings) to the page, saved settings can be used in automated workflows using jcomma, so the specifications do not need to be hand written in JSON.

## Settings which are used in several places

(#hconditions)
### Conditions

* ***empty (no text at all)*** (`"condition": "empty"` [in the spec](#hspec)): there is nothing in the incoming cell, not even a space, or for field either the field does not exist or is an empty string.
* ***whitespace only or empty*** (`"condition": "white"` [in the spec](#hspec)): ignore the row if the specified value is empty, as above, or just contains spaces, tabs, newline or line-feed characters, in any combination.
* ***matches regular expression*** (`"condition": "match", "value": "regexp"` [in the spec](#hspec)): ignore the row if the value matches the provided regular expression, which expresses a pattern to match against. See [regular expressions](#hregularexpression)
* ***does not match regular expression*** (`"condition": "nomatch", "value": "regexp"` [in the spec](#hspec)): ignore the row if the value does **not** match the provided [regular expression](#hregularexpression)
* ***equal to*** (`"condition": "eq", "value": "whatever"` [in the spec](#hspec)): ignore the row if the value is the same as that given. Input cells and output fields which are strings are compared as strings, but fields which are converted to numbers are compared numerically with the given value, so it may be better to use this condition with output fields when numbers are involved.
* ***not equal to*** (`"condition": "ne", "value": "whatever"` [in the spec](#hspec)): ignore the row if the value is not the same as that given. Comparison as for 'equal to'.
* ***greater or equal to*** (`"condition": "ge", "value": 123` [in the spec](#hspec)): the cell or field is >= that given this only makes sense for numbers, so both input and output values are first converted to numbers if necessary, and then compared as numbers.
* ***less or equal to*** (`"condition": "le", "value": 123` [in the spec](#hspec)): <= - numerically, as for 'greater or equal to'.

(#hregularexpression)
### Regular expressions

Regular expressions are a language for expressing the syntax of a string of text. jcomma uses so-called PCRE-regular expressions, [as in PHP](http://php.net/manual/en/book.pcre.php). The regular expression must include the delimiters (any suitable pair of characters) and any trailing modifiers.

See the [PHP manual](http://php.net/manual/en/book.pcre.php) for full details of regular expression syntax.

For example, the following would match a string comprising only the letters A, B, C or a, b, c (using tilde as the delimiter, the trailing modifier 'i' indicating case-independence, ^ and $ requiring start and end of string, and square brackets to indicate a range of characters):

    ~^[a-c]$~i

Note that when you need to escape a character, in a PHP string you often find you need two backslashes, one for PHP's literal string syntax, and another for the regular expression itself. Here, these are not PHP literal strings, so only one is required.

Where replacement is offered, parenthesised matches can be substituted using $1, $2 etc, just as in PHP.

(#hspec)
## JSON specification ("the spec")

The values entered into the form are turned into a JSON object. This can be [saved to a file](#hsavesettings).

When used from the [API](#huseapi), as a [library](#huselibrary), or in a [shell command](#huseshell), the specification is supplied in this structured form.

    {
        outputFormat: "json", # csv, html, xlsx, xml
        outputStyle: "pretty", # for json
        outputBulkElastic: "true", # for json, any non empty value
        outputName: "whatever", # filename, also used to name elements where needed by format
        outputTo: "inline", # or "attachment", or when used as a library, "string"
        outputEncoding: "UTF-8", # or "Windows", for CSV files only (the others are fixed by the file format)
        outXMLElements: "true", # <x><k>v</k>...</x> rather than <x k="v" ...></x>
        encoding: "UTF-8",
        headerRows: 8,
        rowCount: N, # default 1
        ignoreRows: [ # one or more of these (row ignored if any are true):
            {item: "column", name: "A", condition: "...", value: V}, # conditions as before
            {item: "field", name: "name", condition: "...", value: V},
            ...
        ],
        records: [
            {
                fields: [
                    {   name: "...",
                        comprising: [
                            {item: "column",
                            column: "A",
                            rowOffset: N # optional, N=0 by default, otherwise from row relative to current from the N specified for the record in rowCount
                            },
                            {item: "text", text: "whatever"},
                            ...
                        ],
                        options: [ # any of the following, evaluated in turn:
                            {item: "ignoreCurrency", currencies: "pound-sign etc"},
                            {item: "bookkeepersNegative"}, # (123) or 123- => -123
                            {item: "trim"}, # trim surrounding white space
                            {item: "replaceString", matches: "string", output: "substitution"},
                            {item: "replaceRegExp", matches: "regexp", output: "stringwithdollarsubstitutions"},
                            {item: "convertToNumber", errorOnType: true, negate: true}, # any non blank value ok for options
                            {item: "convertToDate", errorOnType: true, dateFormatUS: true, dateFormatTime: true},
                            {item: "omitIf", condition: "match", value: V},
                            {item: "errorOnValue", condition: "match", value: V} # match, eq etc, blank, white, nonNumeric
                        ]
                    } ,
                    ... # more fields
                ],
                unless: [ # generate record from row unless condition is true
                    { field: "name", condition: "eq", value: V},
                    ... # more 'unless' conditions, record discarded if any is true
                ]
            }
            ... # more records (occasionally)
        ]
    }

## Installation

Requires PHP >= 5.4. Does not work on older browsers (it's using a recent version of jQuery).

Just put the files in the document root of your web server, ideally a https website, or as a sub-directory of a website.

You might want to increase the individual file and total file upload limits in your server settings from the PHP default.

## Acknowledgments

Apart from PHP and server software, the only dependencies are [PHP_XLSXWriter](https://github.com/mk-j/PHP_XLSXWriter) for the xlsx file format output, licensed under the MIT license, and [jQuery](https://jquery.com/), also MIT license.



