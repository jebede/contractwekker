#!/bin/bash 
#git diff --cached --name-only | while read FILE; do
#  if [[ "$FILE" =~ ^.+(php|inc|module|install|test)$ ]]; then    
    echo "Running tests..."    
    cd "${0%/*}/.."    
    php vendor/bin/phpunit     
    #echo "output";
    #echo $?;
    #if [ $? -ne 0 ]; then
    if php vendor/bin/phpunit | grep -q 'OK '; then
      echo "Succesful test\n";
    else
      echo -e "\e[1;31m\tUnit tests failed ! Aborting commit.\e[0m" >&2      
      exit 1;    
    fi
#  fi

# Vimexx Deploy trigger
curl -s 'https://www.contractwekker.nl/deploy-script333.php?secret=dsdsaasd43213-dasadsdsa-dsaasdsa';

echo 'Deployment triggered!'

#done ||
exit $?