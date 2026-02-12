<?php
/**
 * Provider de raças por espécie (Fase 7).
 *
 * Dataset de raças de cães e gatos com priorização de populares.
 * Extraído do legado DPS_Registration_Addon::get_breed_dataset().
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Breed_Provider {

    /** @var array<string, array<string, string[]>>|null Cache do dataset. */
    private ?array $cache = null;

    /**
     * Retorna dataset completo de raças por espécie.
     *
     * @return array<string, array<string, string[]>> Formato: [espécie => [popular => [...], all => [...]]]
     */
    public function getAll(): array {
        if ( null !== $this->cache ) {
            return $this->cache;
        }

        $this->cache = [
            'cao'  => [
                'popular' => $this->popularDogs(),
                'all'     => $this->allDogs(),
            ],
            'gato' => [
                'popular' => $this->popularCats(),
                'all'     => $this->allCats(),
            ],
        ];

        return $this->cache;
    }

    /**
     * Retorna raças para uma espécie específica (populares primeiro, depois restantes).
     *
     * @param string $species Espécie: 'cao' ou 'gato'.
     * @return string[] Lista de raças com populares no topo.
     */
    public function getBySpecies( string $species ): array {
        $dataset = $this->getAll();

        if ( ! isset( $dataset[ $species ] ) ) {
            return [];
        }

        $popular   = $dataset[ $species ]['popular'];
        $remaining = array_diff( $dataset[ $species ]['all'], $popular );

        return array_merge( $popular, $remaining );
    }

    /**
     * Retorna dados formatados para JSON (usado pelo JS no datalist).
     *
     * @return array<string, string[]>
     */
    public function toJson(): array {
        $dataset = $this->getAll();

        return [
            'cao'  => $this->getBySpecies( 'cao' ),
            'gato' => $this->getBySpecies( 'gato' ),
        ];
    }

    /**
     * @return string[]
     */
    private function popularDogs(): array {
        return [
            'SRD (Sem Raça Definida)',
            'Shih Tzu',
            'Poodle',
            'Labrador Retriever',
            'Golden Retriever',
            'Buldogue Francês',
            'Yorkshire Terrier',
            'Maltês',
            'Lhasa Apso',
            'Spitz Alemão',
        ];
    }

    /**
     * @return string[]
     */
    private function popularCats(): array {
        return [
            'SRD (Sem Raça Definida)',
            'Siamês',
            'Persa',
            'Maine Coon',
            'Ragdoll',
        ];
    }

    /**
     * @return string[]
     */
    private function allDogs(): array {
        return [
            'Akita',
            'Australian Shepherd',
            'Basset Hound',
            'Beagle',
            'Bichon Frisé',
            'Border Collie',
            'Boston Terrier',
            'Boxer',
            'Buldogue Francês',
            'Buldogue Inglês',
            'Bull Terrier',
            'Cavalier King Charles Spaniel',
            'Chihuahua',
            'Chow Chow',
            'Cocker Spaniel',
            'Dachshund',
            'Dálmata',
            'Doberman',
            'Golden Retriever',
            'Husky Siberiano',
            'Jack Russell Terrier',
            'Labrador Retriever',
            'Lhasa Apso',
            'Maltês',
            'Maltipoo',
            'Pastor Alemão',
            'Pastor Australiano',
            'Pastor Belga',
            'Pequinês',
            'Pinscher',
            'Pit Bull',
            'Pointer',
            'Pomerânia',
            'Poodle',
            'Pug',
            'Rottweiler',
            'Schnauzer',
            'Shar Pei',
            'Shih Tzu',
            'Spitz Alemão',
            'SRD (Sem Raça Definida)',
            'Weimaraner',
            'West Highland White Terrier',
            'Yorkshire Terrier',
        ];
    }

    /**
     * @return string[]
     */
    private function allCats(): array {
        return [
            'Abissínio',
            'American Shorthair',
            'Angorá',
            'Bengal',
            'British Shorthair',
            'Burmês',
            'Chartreux',
            'Devon Rex',
            'Exotic Shorthair',
            'Maine Coon',
            'Munchkin',
            'Norueguês da Floresta',
            'Persa',
            'Ragdoll',
            'Russian Blue',
            'Scottish Fold',
            'Siamês',
            'Siberiano',
            'Sphynx',
            'SRD (Sem Raça Definida)',
        ];
    }
}
