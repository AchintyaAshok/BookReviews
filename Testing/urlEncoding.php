<?php
$encoded = "http://search-add-api.prd.use1.nytimes.com/svc/add/v1/fetch.json?collection=articles&filter=%7B%22meta.filter.document_source%22%3A%22";
$encoded .= urlencode("http://www.nytimes.com/2011/10/12/books/holy-ghost-girl-a-memoir-by-donna-m-johnson-review.html");
$encoded .= "%22%7D"; 
print "URL-encode:\t$encoded\n";
?>
