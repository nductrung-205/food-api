<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {

        $products = [
            // Món chính (category_id = 1)
            [
                'category_id' => 1,
                'name' => 'Phở bò',
                'slug' => 'pho-bo',
                'description' => 'Phở truyền thống Việt Nam với nước dùng trong, thịt bò mềm và bánh phở dai.',
                'price' => 60000,
                'stock' => 120,
                'image' => 'https://static.vinwonders.com/production/pho-bo-ha-noi.jpeg',
            ],
            [
                'category_id' => 1,
                'name' => 'Phở gà',
                'slug' => 'pho-ga',
                'description' => 'Phở gà thanh ngọt, thịt gà dai mềm, rau thơm và hành lá.',
                'price' => 55000,
                'stock' => 100,
                'image' => 'https://static.vinwonders.com/production/Pho-ga-Ha-Noi-9.jpg',
            ],
            [
                'category_id' => 1,
                'name' => 'Bún chả',
                'slug' => 'bun-cha',
                'description' => 'Bún chả Hà Nội với chả nướng, nước mắm chua ngọt, rau sống và bún trắng.',
                'price' => 65000,
                'stock' => 90,
                'image' => 'https://cdn2.fptshop.com.vn/unsafe/1920x0/filters:format(webp):quality(75)/2024_1_12_638406880045931692_cach-lam-bun-cha-ha-noi-0.jpg', // minh hoạ, nên thay bằng ảnh thực
            ],
            [
                'category_id' => 1,
                'name' => 'Bánh xèo',
                'slug' => 'banh-xeo',
                'description' => 'Bánh xèo – bánh gạo giòn rụm, nhân tôm, thịt, giá, ăn cùng rau sống và nước mắm.',
                'price' => 70000,
                'stock' => 80,
                'image' => 'https://cdn.tgdd.vn/2020/12/CookProduct/11-1200x676.jpg',
            ],
            [
                'category_id' => 1,
                'name' => 'Cá kho tộ',
                'slug' => 'ca-kho-to',
                'description' => 'Cá kho trong nồi đất với nước hàng, tiêu và hành lá – món kho đậm đà.',
                'price' => 85000,
                'stock' => 50,
                'image' => 'https://cdnv2.tgdd.vn/mwg-static/common/Common/05052025%20-%202025-05-09T154044.858.jpg', // minh hoạ, nên thay bằng ảnh thực
            ],
            [
                'category_id' => 1,
                'name' => 'Gà xào húng quế',
                'slug' => 'ga-xao-hung-que',
                'description' => 'Gà phi lê xào cùng húng quế, tỏi, ớt – thơm và cay nhẹ.',
                'price' => 78000,
                'stock' => 70,
                'image' => 'https://i.ytimg.com/vi/M9_hH6mRUQk/hq720.jpg?sqp=-oaymwEhCK4FEIIDSFryq4qpAxMIARUAAAAAGAElAADIQj0AgKJD&rs=AOn4CLDBWjTLea1z6CsWnG3RD4HzwN8cjw', // minh hoạ
            ],
            [
                'category_id' => 1,
                'name' => 'Xôi lạp xưởng',
                'slug' => 'xoi-lap-xuong',
                'description' => 'Xôi nếp dẻo thơm kết hợp lạp xưởng, trứng cút và xúc xích Trung Hoa.',
                'price' => 50000,
                'stock' => 60,
                'image' => 'https://cdn.tgdd.vn/Files/2021/09/06/1380707/cach-lam-xoi-lap-xuong-ngon-mem-sieu-nhanh-bang-lo-vi-song-202109062233093121.jpg', // minh hoạ
            ],
            [
                'category_id' => 1,
                'name' => 'Bánh cuốn',
                'slug' => 'banh-cuon',
                'description' => 'Bánh cuốn mỏng với nhân thịt, tôm và nấm, ăn kèm chả lụa và nước chấm.',
                'price' => 65000,
                'stock' => 100,
                'image' => 'https://cdn.tgdd.vn/2021/04/content/banhcuon-800x450.jpg',
            ],
            [
                'category_id' => 1,
                'name' => 'Bánh bao thịt',
                'slug' => 'banh-bao-thit',
                'description' => 'Bánh bao hấp nhân thịt heo, trứng và nấm – món nhẹ nhưng đầy đặn.',
                'price' => 30000,
                'stock' => 200,
                'image' => 'https://banhmibahuynh.vn/wp-content/uploads/2022/11/Banh-bao-ba-Huynh.jpg',
            ],
            [
                'category_id' => 1,
                'name' => 'Cá nướng lá chanh',
                'slug' => 'ca-nuong-la-chanh',
                'description' => 'Cá tươi được ướp tiêu và lá chanh, nướng thơm và giòn lớp da.',
                'price' => 95000,
                'stock' => 40,
                'image' => 'https://thuonghieusanpham.vn/stores/news_dataimages/2023/012023/15/11/in_article/bong-220230115112126.jpg?rt=20230115112127',
            ],

            // Đồ uống (category_id = 2)
            [
                'category_id' => 2,
                'name' => 'Cà phê sữa đá',
                'slug' => 'ca-phe-sua-da',
                'description' => 'Cà phê Việt Nam pha phin, thêm sữa đặc, uống đá – đậm đà và mát lạnh.',
                'price' => 35000,
                'stock' => 300,
                'image' => 'https://ongbi.vn/wp-content/uploads/2023/01/ca-phe-sua-da.jpg',
            ],
            [
                'category_id' => 2,
                'name' => 'Cà phê trứng',
                'slug' => 'ca-phe-trung',
                'description' => 'Cà phê hòa quyện với lớp trứng đánh bông, béo mịn và thơm ngậy.',
                'price' => 50000,
                'stock' => 150,
                'image' => 'https://www.huongnghiepaau.com/wp-content/uploads/2017/07/ca-phe-trung-la-thuc-uong-doc-dao.jpg',
            ],
            [
                'category_id' => 2,
                'name' => 'Trà chanh sả',
                'slug' => 'tra-chanh-sa',
                'description' => 'Trà tươi pha với sả và chanh, thanh mát, thích hợp uống giải nhiệt.',
                'price' => 30000,
                'stock' => 200,
                'image' => 'https://sieuthinguyenlieu.com/assets/uploads/images/W1A57nEO14CX_tra-chanh-sa-web.jpg',
            ],

            [
                'category_id' => 2,
                'name' => 'Sinh tố bơ',
                'slug' => 'sinh-to-bo',
                'description' => 'Sinh tố bơ với sữa đặc và đá, béo ngậy và mát lạnh.',
                'price' => 45000,
                'stock' => 180,
                'image' => 'https://images.prismic.io/nutriinfo/aBHRavIqRLdaBvLz_hinh-anh-sinh-to-bo.jpg?auto=format,compress',
            ],

            // Đồ ăn nhanh (category_id = 3)
            [
                'category_id' => 3,
                'name' => 'Bánh mì thịt',
                'slug' => 'banh-mi-thit',
                'description' => 'Bánh mì Việt Nam với thịt nguội, pate, đồ chua và rau thơm.',
                'price' => 40000,
                'stock' => 220,
                'image' => 'https://www.huongnghiepaau.com/wp-content/uploads/2019/08/banh-mi-kep-thit-nuong-thom-phuc.jpg',
            ],
            [
                'category_id' => 3,
                'name' => 'Gỏi cuốn tôm thịt',
                'slug' => 'goi-cuon-tom-thit',
                'description' => 'Gỏi cuốn tươi gồm tôm, thịt, bún và rau, cuốn chung bánh tráng mềm.',
                'price' => 45000,
                'stock' => 200,
                'image' => 'https://cdn.tgdd.vn/2021/08/CookRecipe/Avatar/goi-cuon-tom-thit-thumbnail-1.jpg',
            ],
            [
                'category_id' => 3,
                'name' => 'Nem rán',
                'slug' => 'nem-ran',
                'description' => 'Nem chua rán giòn, nhân thịt và rau củ, chấm nước mắm hoặc tương ớt.',
                'price' => 50000,
                'stock' => 150,
                'image' => 'https://cdn.tgdd.vn/2022/10/CookDishThumb/cach-lam-mon-nem-ran-thom-ngon-chuan-vi-don-gian-tai-nha-thumb-620x620.jpg',
            ],
            [
                'category_id' => 3,
                'name' => 'Bánh tôm Hồ Tây',
                'slug' => 'banh-tom-ho-tay',
                'description' => 'Bánh tôm tẩm bột chiên giòn, ăn kèm rau sống và nước chấm chua ngọt.',
                'price' => 65000,
                'stock' => 90,
                'image' => 'https://cdn.xanhsm.com/2024/12/dfdc574a-banh-tom-ho-tay-18.jpg',
            ],

            // Tráng miệng (category_id = 4)
            [
                'category_id' => 4,
                'name' => 'Chè bà ba',
                'slug' => 'che-ba-ba',
                'description' => 'Chè bà ba với khoai lang, khoai môn, đậu và nước cốt dừa thơm béo. ',
                'price' => 40000,
                'stock' => 120,
                'image' => 'https://i.ytimg.com/vi/cw-HNzIqgK0/maxresdefault.jpg',
            ],
            [
                'category_id' => 4,
                'name' => 'Chè trôi nước',
                'slug' => 'che-troi-nuoc',
                'description' => 'Bánh trôi nước từ nếp, nhân đậu xanh, nước gừng ngọt ấm và mè rang. ',
                'price' => 35000,
                'stock' => 100,
                'image' => 'https://cdn.tgdd.vn/2021/09/CookProduct/1200-1200x676-71.jpg',
            ],
            [
                'category_id' => 4,
                'name' => 'Bánh chuối nướng',
                'slug' => 'banh-chuoi-nuong',
                'description' => 'Bánh chuối nướng dẻo thơm, vị ngọt dịu của chuối và nước cốt dừa.',
                'price' => 30000,
                'stock' => 80,
                'image' => 'https://cdn.tgdd.vn/Files/2019/12/04/1224657/4-cach-lam-banh-chuoi-nuong-thom-ngon-12-760x367.jpg',
            ],
            [
                'category_id' => 4,
                'name' => 'Kem dừa',
                'slug' => 'kem-dua',
                'description' => 'Kem làm từ nước dừa tươi, mát lạnh và thơm nhẹ hương dừa.',
                'price' => 45000,
                'stock' => 150,
                'image' => 'https://file.hstatic.net/200000721249/file/cach_lam_kem_dua_matcha_92c03c90fe6c4e22806e1126feedc319.jpg',
            ],


        ];

        foreach ($products as $p) {
            Product::create($p);
        }

        $total = 10000;

        for ($i = 1; $i <= $total; $i++) {
            Product::factory()->create([
                'name' => "Sản phẩm $i",
                'slug' => "san-pham-$i",
            ]);
        }
    }
}
