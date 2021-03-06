<?php

namespace AutoMapperPlus\AutoMapperPlusBundle\Tests\PropertyAccessor;

use AutoMapperPlus\AutoMapper;
use AutoMapperPlus\AutoMapperPlusBundle\PropertyAccessor\DecoratedSymfonyPropertyAccessorBridge;
use AutoMapperPlus\AutoMapperPlusBundle\PropertyAccessor\SymfonyPropertyAccessorBridge;
use AutoMapperPlus\AutoMapperPlusBundle\Tests\Helper\AddressDTO;
use AutoMapperPlus\AutoMapperPlusBundle\Tests\Helper\Address;
use AutoMapperPlus\AutoMapperPlusBundle\Tests\Helper\Destination;
use AutoMapperPlus\AutoMapperPlusBundle\Tests\Helper\Person;
use AutoMapperPlus\AutoMapperPlusBundle\Tests\Helper\PersonDTO;
use AutoMapperPlus\AutoMapperPlusBundle\Tests\Helper\Source;
use AutoMapperPlus\Configuration\AutoMapperConfig;
use AutoMapperPlus\MappingOperation\Operation;
use AutoMapperPlus\PropertyAccessor\PropertyAccessorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

abstract class PropertyAccessorTestBase extends TestCase
{
    /**
     * @var AutoMapper
     */
    protected $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $propertyAccessor = $this->getPropertyAccessor();
        $config = new AutoMapperConfig();
        $config->getOptions()->setPropertyAccessor($propertyAccessor);

        $config->registerMapping(Person::class, PersonDTO::class)
          ->forMember('adr', Operation::fromProperty('address')->mapTo(AddressDTO::class))
          ->forMember('children', Operation::mapCollectionTo(PersonDTO::class))
          ->dontSkipConstructor()
          ->reverseMap()
          ->forMember('address', Operation::fromProperty('adr')->mapTo(Address::class));

        $config->registerMapping(Address::class, AddressDTO::class)
          ->reverseMap();

        $config->registerMapping(Source::class, Destination::class)
            ->forMember('name', Operation::fromProperty('name'));

        $this->mapper = new AutoMapper($config);
    }

    abstract protected function getPropertyAccessor(): PropertyAccessorInterface;

    public function testNestedMapping() {
        $address = new Address();
        $address->street = 'Testing Road';
        $address->number = '1';
        $children = [
          (new Person())->setName('Son')->setAddress($address),
          (new Person())->setName('Jane')->setAddress($address),
        ];
        $person = new Person();
        $person->setName('Tester')
          ->setChildren($children)
          ->setAge(1)
          ->setAddress($address);

        /** @var PersonDTO $dto */
        $dto = $this->mapper->map($person, PersonDTO::class);

        self::assertEquals('Tester', $dto->getName());
        self::assertEquals('Testing Road', $dto->getAdr()->getStreet());

        self::assertIsArray($dto->getChildren());
        self::assertEquals('Son', $dto->getChildren()[0]->getName());
        self::assertEquals('Jane', $dto->getChildren()[1]->getName());
    }

    public function testReverseMapping() {
        $address = new AddressDTO();
        $address->setStreet('Testing Road');
        $children = [
          (new PersonDTO())->setName('Son')->setAdr($address),
          (new PersonDTO())->setName('Jane')->setAdr($address),
        ];
        $dto = new PersonDTO();
        $dto->setName('Tester')
          ->setChildren($children)
          ->setAdr($address);

        /** @var Person $person */
        $person = $this->mapper->map($dto, Person::class);

        self::assertEquals('Tester', $person->getName());
        self::assertEquals('Testing Road', $person->getAddress()->street);

        self::assertIsArray($person->getChildren());
        self::assertEquals('Son', $person->getChildren()[0]->getName());
        self::assertEquals('Jane', $person->getChildren()[1]->getName());
    }
}
