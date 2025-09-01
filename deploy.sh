#!/bin/bash 
echo "Running tests..."    
cd "${0%/*}"
php tests/run-tests.php
if [ $? -eq 0 ]; then
  echo -e "\e[1;32m✓ All tests passed!\e[0m";
else
  echo -e "\e[1;31m✗ Tests failed! Aborting deployment.\e[0m" >&2      
  exit 1;    
fi

# Vimexx Deploy trigger
curl -s 'https://contractwekker.nl/deploy-script222?secret=dsdsa-asd32189AA43213-dasadsdsa-dsaasdsa';

echo 'Deployment triggered!'

#done ||
exit $?