#!/bin/bash
# Upload the updated category_check.php via curl

curl -T "/home/shuubb/Desktop/Main Codebase/Snusflix/category_check.php" \
     --ftp-create-dirs \
     --user "snuszibe_snus:Azerty123" \
     "ftp://ftp.snusflix.com/category_check.php"