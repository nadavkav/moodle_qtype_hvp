# Question Type HVP

Code copied from mod/hvp and customized to fit as a question type

## Upgrading library/ & editor/, reporting/

Imported from:
- https://github.com/h5p/h5p-php-library.git
- https://github.com/h5p/h5p-editor-php-library.git
- https://github.com/h5p/h5p-php-report.git - only h5p-report-xapi-data.class.php was needed.
Respectively

We tried to not touch those, some changes had to be done though:
- Grading:
  - library/js/h5p-x-api-event.js 
  - library/js/h5p-x-api-event.js
  modified to communicate grades through xapi
  
- Some changes had to be made to not class with mod\hvp if installed, namely:
  - Wrap library types in namespace qtype_hvp_library

