<?php
/**
 * Registro centralizado de raças (breed registry).
 *
 * Extraído de DPS_Base_Frontend para reutilização por outros módulos.
 *
 * @package DesiPetShower
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Breed_Registry {

    /**
     * Retorna dataset de raças por espécie, incluindo lista de populares.
     *
     * @since 1.0.0
     * @return array
     */
    public static function get_dataset() {
        static $dataset = null;

        if ( null !== $dataset ) {
            return $dataset;
        }

        $dog_breeds = [
            'SRD (Sem Raça Definida)',
            'Affenpinscher',
            'Afghan Hound',
            'Airedale Terrier',
            'Akita',
            'Alaskan Malamute',
            'American Bulldog',
            'American Cocker Spaniel',
            'American Pit Bull Terrier',
            'American Staffordshire Terrier',
            'Basenji',
            'Basset Hound',
            'Beagle',
            'Bearded Collie',
            'Belgian Malinois',
            'Bernese Mountain Dog (Boiadeiro Bernês)',
            'Bichon Frisé',
            'Bichon Havanês',
            'Bloodhound',
            'Boiadeiro Australiano',
            'Border Collie',
            'Borzói',
            'Boston Terrier',
            'Boxer',
            'Bulldog',
            'Bulldog Americano',
            'Bulldog Campeiro',
            'Bulldog Francês',
            'Bulldog Inglês',
            'Bull Terrier',
            'Bullmastiff',
            'Cairn Terrier',
            'Cane Corso',
            'Cão Afegão',
            'Cão de Água Português',
            'Cão de Crista Chinês',
            'Cão de Pastor Alemão (Pastor Alemão)',
            'Cão de Pastor Shetland',
            'Cavalier King Charles Spaniel',
            'Chesapeake Bay Retriever',
            'Chihuahua',
            'Chow Chow',
            'Cocker Spaniel',
            'Collie',
            'Coton de Tulear',
            'Dachshund (Teckel)',
            'Dálmata',
            'Dobermann',
            'Dogo Argentino',
            'Dogue Alemão',
            'Fila Brasileiro',
            'Fox Paulistinha',
            'Galgo Inglês',
            'Golden Retriever',
            'Greyhound',
            'Husky Siberiano',
            'Irish Setter',
            'Irish Wolfhound',
            'Jack Russell Terrier',
            'Kelpie Australiano',
            'Kerry Blue Terrier',
            'Labradoodle',
            'Labrador Retriever',
            'Lhasa Apso',
            'Lulu da Pomerânia (Spitz Alemão)',
            'Malamute do Alasca',
            'Maltês',
            'Mastiff Inglês',
            'Mastim Tibetano',
            'Old English Sheepdog (Bobtail)',
            'Papillon',
            'Pastor Australiano',
            'Pastor Belga Malinois',
            'Pastor de Shetland',
            'Pequinês',
            'Pinscher',
            'Pinscher Miniatura',
            'Pit Bull Terrier',
            'Podengo Português',
            'Poodle',
            'Poodle Toy',
            'Pug',
            'Rottweiler',
            'Samoieda',
            'Schnauzer',
            'Scottish Terrier',
            'Serra da Estrela',
            'Shar Pei',
            'Shiba Inu',
            'Shih Tzu',
            'Spitz Japonês',
            'Springer Spaniel Inglês',
            'Staffordshire Bull Terrier',
            'Terra-Nova',
            'Vira-lata',
            'Weimaraner',
            'Welsh Corgi Pembroke',
            'Whippet',
            'Yorkshire Terrier',
        ];

        $cat_breeds = [
            'SRD (Sem Raça Definida)',
            'Abissínio',
            'Angorá Turco',
            'Azul Russo',
            'Bengal',
            'Birmanês',
            'British Shorthair',
            'Chartreux',
            'Cornish Rex',
            'Devon Rex',
            'Exótico de Pelo Curto',
            'Himalaio',
            'LaPerm',
            'Maine Coon',
            'Manx',
            'Munchkin',
            'Norueguês da Floresta',
            'Ocicat',
            'Oriental de Pelo Curto',
            'Persa',
            'Ragdoll',
            'Sagrado da Birmânia',
            'Savannah',
            'Scottish Fold',
            'Selkirk Rex',
            'Siamês',
            'Siberiano',
            'Singapura',
            'Somali',
            'Sphynx',
            'Tonquinês',
            'Toyger',
            'Van Turco',
        ];

        $dataset = [
            'cao'  => [
                'popular' => [ 'SRD (Sem Raça Definida)', 'Shih Tzu', 'Poodle', 'Labrador Retriever', 'Golden Retriever' ],
                'all'     => $dog_breeds,
            ],
            'gato' => [
                'popular' => [ 'SRD (Sem Raça Definida)', 'Siamês', 'Persa', 'Maine Coon', 'Ragdoll' ],
                'all'     => $cat_breeds,
            ],
        ];

        $dataset['all'] = [
            'popular' => array_values( array_unique( array_merge( $dataset['cao']['popular'], $dataset['gato']['popular'] ) ) ),
            'all'     => array_values( array_unique( array_merge( $dog_breeds, $cat_breeds ) ) ),
        ];

        return $dataset;
    }

    /**
     * Monta lista de opções de raças para a espécie selecionada.
     *
     * @since 1.0.0
     * @param string $species Código da espécie (cao/gato/outro).
     * @return array Lista ordenada com populares primeiro.
     */
    public static function get_options_for_species( $species ) {
        $dataset  = self::get_dataset();
        $selected = isset( $dataset[ $species ] ) ? $dataset[ $species ] : $dataset['all'];
        $merged   = array_merge( $selected['popular'], $selected['all'] );

        return array_values( array_unique( $merged ) );
    }
}
