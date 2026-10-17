<?php

namespace Tests\Unit\Search;

use App\Services\Search\DeterministicMockEmbeddingGenerator;
use PHPUnit\Framework\TestCase;

final class DeterministicMockEmbeddingGeneratorTest extends TestCase
{
    public function test_generates_exact_same_vector_for_identical_seeds(): void
    {
        $generator = new DeterministicMockEmbeddingGenerator(dimension: 512);

        $vector1 = $generator->generateVectorFromString('image_content_abc');
        $vector2 = $generator->generateVectorFromString('image_content_abc');

        $this->assertCount(512, $vector1);
        $this->assertCount(512, $vector2);
        $this->assertSame($vector1, $vector2);
    }

    public function test_generates_different_vectors_for_different_seeds(): void
    {
        $generator = new DeterministicMockEmbeddingGenerator(dimension: 512);

        $vector1 = $generator->generateVectorFromString('seed_apple');
        $vector2 = $generator->generateVectorFromString('seed_orange');

        $this->assertNotSame($vector1, $vector2);
    }

    public function test_vector_is_l2_normalized_to_unit_length(): void
    {
        $generator = new DeterministicMockEmbeddingGenerator(dimension: 256);
        $vector = $generator->generateVectorFromString('sample_image_test_vector');

        $sumOfSquares = 0.0;
        foreach ($vector as $val) {
            $sumOfSquares += $val * $val;
        }

        $magnitude = sqrt($sumOfSquares);
        // Magnitude should be very close to 1.0 (unit vector)
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.005);
    }
}
