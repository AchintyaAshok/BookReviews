FILE_DIRECTORY=$1
cd $FILE_DIRECTORY

for i in *
do
php ../find_bookless_reviews.php $i
done


