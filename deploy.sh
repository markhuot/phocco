#!/bin/bash

cp resources/phocco.css docs/phocco.css
cp resources/showdown.js docs/showdown.js

git checkout gh-pages

cp docs/phocco.css resources/
cp docs/showdown.js resources/

cp docs/phocco.php.html .
cp docs/phocco.php.html index.html
cp docs/resources/showdown.js.html resources/
cp docs/resources/phocco.css.html resources/

git commit -am "updates from master"
git push origin gh-pages

git checkout master
