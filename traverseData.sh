dirname=$1
cd $dirname


for i in *
do
echo $i
php ../find_bookless_reviews.php $i
done


