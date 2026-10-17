import '../models/category_model.dart';
import '../models/currency_model.dart';
import '../models/order_model.dart';
import '../models/product_model.dart';

class MockData {
  MockData._();

  static const List<CurrencyModel> currencies = [
    CurrencyModel(
      id: 1,
      code: 'YER',
      symbol: 'ر.ي',
      nameAr: 'ريال يمني',
      isDefault: true,
    ),
    CurrencyModel(
      id: 2,
      code: 'SAR',
      symbol: 'ر.س',
      nameAr: 'ريال سعودي',
    ),
    CurrencyModel(
      id: 3,
      code: 'USD',
      symbol: '\$',
      nameAr: 'دولار أمريكي',
    ),
  ];

  static const List<CategoryModel> categories = [
    CategoryModel(
      id: ProductCategory.perfumes,
      nameAr: 'عطور',
      nameEn: 'Perfumes',
      icon: 'Sparkles',
      image:
          'https://images.unsplash.com/photo-1547887537-6158d64c35b3?q=80&w=800&auto=format&fit=crop',
      count: 48,
    ),
    CategoryModel(
      id: ProductCategory.makeup,
      nameAr: 'مكياج',
      nameEn: 'Makeup',
      icon: 'Heart',
      image:
          'https://images.unsplash.com/photo-1596462502278-27bfdc403348?q=80&w=800&auto=format&fit=crop',
      count: 64,
    ),
    CategoryModel(
      id: ProductCategory.skincare,
      nameAr: 'العناية بالبشرة',
      nameEn: 'Skincare',
      icon: 'Droplet',
      image:
          'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?q=80&w=800&auto=format&fit=crop',
      count: 52,
    ),
    CategoryModel(
      id: ProductCategory.haircare,
      nameAr: 'العناية بالشعر',
      nameEn: 'Haircare',
      icon: 'Feather',
      image:
          'https://images.unsplash.com/photo-1527799820374-dcf8d9d4a388?q=80&w=800&auto=format&fit=crop',
      count: 36,
    ),
    CategoryModel(
      id: ProductCategory.salon,
      nameAr: 'مستلزمات صالونات',
      nameEn: 'Salon Supplies',
      icon: 'Scissors',
      image:
          'https://images.unsplash.com/photo-1560066984-138dadb4c035?q=80&w=800&auto=format&fit=crop',
      count: 29,
    ),
    CategoryModel(
      id: ProductCategory.gifts,
      nameAr: 'هدايا وباكجات',
      nameEn: 'Gifts & Bundles',
      icon: 'Gift',
      image:
          'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?q=80&w=800&auto=format&fit=crop',
      count: 22,
    ),
  ];

  static const List<ProductModel> products = [
    ProductModel(
      id: 'be-perfume-01',
      name: 'عطر رويال عنبر الملكي الإكستريت',
      brand: 'B&E Private Reserve',
      category: ProductCategory.perfumes,
      subCategory: 'عطور شرقية نيشان',
      priceSAR: 580.0,
      originalPriceSAR: 720.0,
      discountPercentage: 20,
      rating: 4.9,
      reviewsCount: 142,
      image:
          'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?q=80&w=800&auto=format&fit=crop',
      images: [
        'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?q=80&w=800&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1547887537-6158d64c35b3?q=80&w=800&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1523293182086-7651a899d37f?q=80&w=800&auto=format&fit=crop',
      ],
      description:
          'توليفة ملكية فخمة تبتدئ بنفحات الهيل الغواتيمالي والبرغموت الإيطالي النادر، تليها باقة دافئة من العنبر الرمادي والعود الكمبودي المعتق مع لمسات ناعمة من الفانيليا التاهيتية.',
      notes: FragranceNotes(
        top: 'الهيل الذهبي، البرغموت الصقلي، الزعفران الكشميري',
        heart: 'العنبر الأبيض، الورد الدمشقي، خشب الصندل الميسوري',
        base: 'العود الكمبودي الملكي، الجلد الفاخر، مسك تاهيتي',
      ),
      volumeOrSize: ['50 مل', '100 مل', '125 مل فاخر مع حقيبة مخملية'],
      inStock: true,
      isBestSeller: true,
      isExclusive: true,
    ),
    ProductModel(
      id: 'be-skincare-01',
      name: 'سيروم الببتيد والذهب عيار 24 لإشراقة فورية',
      brand: 'L’Élixir De Beauté',
      category: ProductCategory.skincare,
      subCategory: 'عناية مركزة مضادة للتجاعيد',
      priceSAR: 360.0,
      originalPriceSAR: 420.0,
      rating: 4.8,
      reviewsCount: 89,
      image:
          'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?q=80&w=800&auto=format&fit=crop',
      images: [
        'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?q=80&w=800&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1608248597359-2166946059d3?q=80&w=800&auto=format&fit=crop',
      ],
      description:
          'تركيبة مبتكرة فائقة التغلغل تجمع بين جزيئات الذهب الخالص عيار 24 وثمانية أنواع من حمض الهيالورونيك المركّز لشد البشرة وتغذيتها بعمق وإضفاء نضارة زجاجية ساحرة.',
      features: [
        'شد فوري ومرونة تدوم حتى 24 ساعة',
        'تعزيز إنتاج الكولاجين الطبيعي بنسبة 68%',
        'خالٍ من العطور والبارابين ومناسب للبشرة الحساسة',
      ],
      volumeOrSize: ['30 مل', '50 مل'],
      inStock: true,
      isNew: true,
    ),
    ProductModel(
      id: 'be-makeup-01',
      name: 'أحمر شفاه فلفت مات المخملي فائق الثبات',
      brand: 'Maison Étoile',
      category: ProductCategory.makeup,
      subCategory: 'أحمر شفاه كوتور',
      priceSAR: 195.0,
      rating: 4.9,
      reviewsCount: 230,
      image:
          'https://images.unsplash.com/photo-1586495777744-4413f21062fa?q=80&w=800&auto=format&fit=crop',
      images: [
        'https://images.unsplash.com/photo-1586495777744-4413f21062fa?q=80&w=800&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1596462502278-27bfdc403348?q=80&w=800&auto=format&fit=crop',
      ],
      description:
          'ملمس كالحرير بلون غني من لمسة واحدة فقط. تغطية مخملية مطفية بدون أن تسبب جفاف الشفاه، مدعمة بزيت الكاميليا العضوي وزبدة الشيا.',
      shades: [
        ProductShade(name: '01 أحمر كوتور كلاسيكي', hex: '#8C1D2F'),
        ProductShade(name: '02 نود كشمير هادئ', hex: '#B57C76'),
        ProductShade(name: '03 تيراكوتا مخملي', hex: '#A85A48'),
        ProductShade(name: '04 بيري ملكي عميق', hex: '#581825'),
      ],
      inStock: true,
      isBestSeller: true,
    ),
    ProductModel(
      id: 'be-perfume-02',
      name: 'عطر مسك بلانش نيرولي المنعش',
      brand: 'B&E Private Reserve',
      category: ProductCategory.perfumes,
      subCategory: 'عطور الأزهار المنعشة',
      priceSAR: 490.0,
      rating: 4.7,
      reviewsCount: 76,
      image:
          'https://images.unsplash.com/photo-1588405748880-12d1d2a59f75?q=80&w=800&auto=format&fit=crop',
      images: [
        'https://images.unsplash.com/photo-1588405748880-12d1d2a59f75?q=80&w=800&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1523293182086-7651a899d37f?q=80&w=800&auto=format&fit=crop',
      ],
      description:
          'رائحة نقية تعكس أناقة الصباح الباكر في حدائق إيطاليا. مسك أبيض بلوري ممزوج بزهر البرتقال والنيرولي النقي وأخشاب الأرز العطرية.',
      notes: FragranceNotes(
        top: 'زهر البرتقال (النيرولي)، البرغموت، الماندرين الأخضر',
        heart: 'الياسمين السامباك، الزنبق الأبيض، الشاي الأبيض',
        base: 'المسك الأبيض الكريستالي، خشب الأرز الأطلسي',
      ),
      volumeOrSize: ['100 مل'],
      inStock: true,
    ),
    ProductModel(
      id: 'be-haircare-01',
      name: 'زيت الكافيار والأرغان لترميم وتكثيف الشعر التالف',
      brand: 'Botanique Royale',
      category: ProductCategory.haircare,
      subCategory: 'علاج مكثف للصالونات',
      priceSAR: 275.0,
      originalPriceSAR: 330.0,
      discountPercentage: 15,
      rating: 4.9,
      reviewsCount: 94,
      image:
          'https://images.unsplash.com/photo-1608248597297-b6730623f990?q=80&w=800&auto=format&fit=crop',
      images: [
        'https://images.unsplash.com/photo-1608248597297-b6730623f990?q=80&w=800&auto=format&fit=crop',
      ],
      description:
          'إكسير سائل غني بمستخلص الكافيار الأسود وزيت الأرغان المغربي النقي 100%. يعيد للشعر الباهت بريقه وقوته ويحمي من حرارة التصفيف.',
      features: [
        'حماية فائقة من الحرارة وتقصف الأطراف',
        'لمعان حريري فوري دون أي ملمس دهني',
        'رائحة عطرية آسرة تدوم حتى 48 ساعة',
      ],
      volumeOrSize: ['100 مل', '150 مل'],
      inStock: true,
      isBestSeller: true,
    ),
    ProductModel(
      id: 'be-gifts-01',
      name: 'صندوق الهدايا الإمبراطوري الفاخر (مجموعة بيوتي آند إليجانس)',
      brand: 'B&E Haute Parfumerie',
      category: ProductCategory.gifts,
      subCategory: 'باقات هدايا حصرية',
      priceSAR: 1250.0,
      originalPriceSAR: 1550.0,
      discountPercentage: 20,
      rating: 5.0,
      reviewsCount: 68,
      image:
          'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?q=80&w=800&auto=format&fit=crop',
      images: [
        'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?q=80&w=800&auto=format&fit=crop',
        'https://images.unsplash.com/photo-1549465220-1a8b9238cd48?q=80&w=800&auto=format&fit=crop',
      ],
      description:
          'الصندوق الأرقى للإهداء. يضم عطر رويال عنبر 100 مل، شمعة عطرية فاخرة بفتيلين من الصويا الطبيعية، وسيروم الكولاجين الملكي مع بطاقة إهداء مذهبة.',
      volumeOrSize: ['صندوق إمبراطوري كامل'],
      inStock: true,
      isExclusive: true,
    ),
    ProductModel(
      id: 'be-salon-01',
      name: 'مجموعة التصفيف الحراري الاحترافية للصالونات برو 2400W',
      brand: 'Atelier Salon Pro',
      category: ProductCategory.salon,
      subCategory: 'أجهزة الصالونات الاحترافية',
      priceSAR: 950.0,
      originalPriceSAR: 1200.0,
      discountPercentage: 21,
      rating: 4.8,
      reviewsCount: 52,
      image:
          'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=800&auto=format&fit=crop',
      images: [
        'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=800&auto=format&fit=crop',
      ],
      description:
          'مجفف ومصفف بتقنية الأيونات الذكية ومحرك فائق القوة صمم خصيصاً لجلسات الصالونات الطويلة. يقلل زمن التجفيف بمعدل 50% ويمنع التطاير.',
      volumeOrSize: ['طقم احترافي كامل (3 رؤوس سيراميك)'],
      inStock: true,
      isExclusive: true,
    ),
  ];

  static final List<OrderModel> initialOrders = [
    const OrderModel(
      id: 'ord-9041',
      orderNumber: 'BE-2026-9041',
      date: '2026-09-15',
      status: OrderStatus.withCourier,
      statusAr: 'مع مندوب التوصيل',
      subtotal: 940.0,
      shipping: 0.0,
      discount: 50.0,
      total: 890.0,
      currencyCode: CurrencyCode.SAR,
      paymentMethod: 'Apple Pay',
      shippingAddress: ShippingAddressModel(
        recipientName: 'نورة السبيعي',
        phone: '+966 50 123 4567',
        city: 'الرياض',
        district: 'حي حطين',
        street: 'طريق الأمير تركي الأول',
      ),
      estimatedDelivery: 'اليوم بين 4:00 م - 8:00 م',
      trackingNumber: 'SA-EXP-8849201',
      courierName: 'أرامكس إكسبريس المبرد',
      items: [
        OrderItemModel(
          id: 'item-1',
          productName: 'عطر رويال عنبر الملكي الإكستريت',
          brand: 'B&E Private Reserve',
          quantity: 1,
          price: 580.0,
          image:
              'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?q=80&w=800&auto=format&fit=crop',
          sizeOrShade: '100 مل',
        ),
        OrderItemModel(
          id: 'item-2',
          productName: 'سيروم الببتيد والذهب عيار 24',
          brand: 'L’Élixir De Beauté',
          quantity: 1,
          price: 360.0,
          image:
              'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?q=80&w=800&auto=format&fit=crop',
          sizeOrShade: '30 مل',
        ),
      ],
    ),
  ];
}
