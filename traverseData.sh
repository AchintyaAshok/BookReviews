dirname=$1
cd $dirname


for i in *
do
php ../find_bookless_reviews.php $i
done


