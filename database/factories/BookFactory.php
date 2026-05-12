<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    protected static array $categoryIds = [];

    protected static int $sequence = 200000000;

    protected static array $publishers = [
        'Apress',
        'Cengage Learning',
        'HarperCollins',
        'Manning Publications',
        'McGraw Hill',
        'No Starch Press',
        'O Reilly Media',
        'Packt Publishing',
        'Pearson Education',
        'Penguin Random House',
        'Pragmatic Bookshelf',
        'Springer',
        'Wiley',
        'World Scientific',
        'Que Publishing',
    ];

    public function definition(): array
    {
        $sequence = self::$sequence++;
        $format = $this->faker->randomElement(['paperback', 'hardcover', 'ebook', 'audiobook']);
        $title = $this->bookTitle($sequence);
        $publishedAt = $this->faker->dateTimeBetween('-35 years', '+1 year');

        return [
            'category_id' => $this->faker->randomElement($this->categoryIds()),
            'title' => $title,
            'slug' => Str::slug($title).'-'.$sequence,
            'author' => $this->faker->name(),
            'publisher' => $this->faker->randomElement(self::$publishers),
            'format' => $format,
            'isbn' => $this->generateValidIsbn13($sequence),
            'description' => $this->faker->paragraphs($this->faker->numberBetween(1, 3), true),
            'price' => $this->priceForFormat($format),
            'stock' => $this->faker->numberBetween(0, 1000),
            'cover_image' => null,
            'published_at' => $publishedAt->format('Y-m-d'),
            'status' => $this->faker->boolean(85) ? 'active' : 'inactive',
            'is_featured' => $this->faker->boolean(8),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function bestseller(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $this->faker->numberBetween(500, 2000),
            'status' => 'active',
            'is_featured' => true,
        ]);
    }

    public static function resetSequence(int $start = 200000000): void
    {
        self::$sequence = $start;
    }

    public static function clearCategoryCache(): void
    {
        self::$categoryIds = [];
    }

    protected function categoryIds(): array
    {
        if (self::$categoryIds === []) {
            self::$categoryIds = Category::query()->pluck('id')->all();
        }

        if (self::$categoryIds === []) {
            throw new \RuntimeException('BookFactory requires at least one category record.');
        }

        return self::$categoryIds;
    }

    protected function bookTitle(int $sequence): string
    {
        $patterns = [
            'Practical %s Handbook %d',
            'Modern %s Essentials %d',
            'Complete Guide to %s %d',
            '%s for Professionals %d',
            'Applied %s Patterns %d',
        ];

        $topic = $this->faker->randomElement([
            'Laravel',
            'Databases',
            'Software Design',
            'Business Strategy',
            'World History',
            'Physics',
            'Study Skills',
            'Digital Marketing',
            'Data Science',
            'Creative Writing',
        ]);

        return sprintf($this->faker->randomElement($patterns), $topic, $sequence);
    }

    protected function priceForFormat(string $format): float
    {
        $range = match ($format) {
            'hardcover' => [850, 2499],
            'ebook' => [199, 899],
            'audiobook' => [299, 1199],
            default => [350, 1499],
        };

        return (float) $this->faker->randomFloat(2, $range[0], $range[1]);
    }

    protected function generateValidIsbn13(int $sequence): string
    {
        $body = '978'.str_pad((string) ($sequence % 1000000000), 9, '0', STR_PAD_LEFT);
        $sum = 0;

        foreach (str_split($body) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        $checksum = (10 - ($sum % 10)) % 10;

        return $body.$checksum;
    }
}
