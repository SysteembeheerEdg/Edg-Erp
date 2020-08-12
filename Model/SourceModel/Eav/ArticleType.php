<?php
namespace Edg\Erp\Model\SourceModel\Eav;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class ArticleType extends AbstractSource
{
    const TYPE_HOOFDARTIKEL = 1;
    const TYPE_ONDERDEEL = 2;
    const TYPE_NO_STOCK = 3;
    const TYPE_BUNDELARTIKEL = 4;
    const TYPE_GROEPARTIKEL = 5;
    const TYPE_TEKSTREGEL = 6;
    const TYPE_VERZENDKOSTEN = 7;
    /**
     * @var array $options
     */
    protected $options;

    /**
     * Article types (as defiend by EDG/Progress)
     */
    protected $_articleTypes = [
        self::TYPE_HOOFDARTIKEL => 'Hoofdartikel',
        self::TYPE_ONDERDEEL => 'Onderdeel',
        self::TYPE_NO_STOCK => 'No Stock',
        self::TYPE_BUNDELARTIKEL => 'Bundelartikel',
        self::TYPE_GROEPARTIKEL => 'Groepartikel',
        self::TYPE_TEKSTREGEL => 'Tekstregel',
        self::TYPE_VERZENDKOSTEN => 'Verzendkosten',
    ];

    /**
     * @var array
     */
    protected $_nonShippableArticleTypes = [
        self::TYPE_NO_STOCK,
        self::TYPE_GROEPARTIKEL,
        self::TYPE_TEKSTREGEL,
        self::TYPE_VERZENDKOSTEN
    ];

    /**
     * Get Options
     *
     * @return array
     */
    public function getAllOptions()
    {
        if ($this->options == null) {

            $temp = [];
            $temp[] = ['value' => null, 'label' => __('-- No articletype set --')];

            foreach ($this->_articleTypes as $value => $label) {
                $temp[] = ['value' => $value, 'label' => $label];
            }
            $this->options = $temp;
        }

        return $this->options;
    }


    /**
     * @return array
     */
    public function getNonShippableArticleTypes()
    {
        return $this->_nonShippableArticleTypes;
    }
}