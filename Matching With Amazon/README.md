This folder consists of 3 things I had to do to facilitate the addition of book reviews to NYTimes databases.

a) Lead Paragraph Reviews:
      This folder has the script that extracted Title & author information from the NYTimes databases using the lead paragraph entries generated in JSON format when a call is made to the company's ADDIndex servers for Book Reviews.
      
b) Glass Reviews:
      In addition to the ADD Index, Glass is another version of storage that the NYTimes adopted that better categorizes article information for the Times. The script present in this folder gets book review information that was returned by the Glass API.
      
c) Cleanup:
      This folder consists of scripts that were primarily used to "clean" the extracted information that was received from the Glass and Lead Paragraph Reviews API. They account for many of the edge cases that arise from non-optimal indexing of information that was performed when the Times moved into digitally storing their Book Reviews. 
