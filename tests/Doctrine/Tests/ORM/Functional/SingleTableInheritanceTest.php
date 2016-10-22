<?php

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FetchMode;
use Doctrine\ORM\Persisters\PersisterException;
use Doctrine\ORM\Proxy\Proxy;
use Doctrine\Tests\Models\Company\CompanyContract;
use Doctrine\Tests\Models\Company\CompanyEmployee;
use Doctrine\Tests\Models\Company\CompanyFixContract;
use Doctrine\Tests\Models\Company\CompanyFlexContract;
use Doctrine\Tests\Models\Company\CompanyFlexUltraContract;
use Doctrine\Tests\OrmFunctionalTestCase;

class SingleTableInheritanceTest extends OrmFunctionalTestCase
{
    private $salesPerson;
    private $engineers = [];
    private $fix;
    private $flex;
    private $ultra;

    public function setUp()
    {
        $this->useModelSet('company');
        parent::setUp();
    }

    public function persistRelatedEmployees()
    {
        $this->salesPerson = new CompanyEmployee();
        $this->salesPerson->setName('Poor Sales Guy');
        $this->salesPerson->setDepartment('Sales');
        $this->salesPerson->setSalary(100);

        $engineer1 = new CompanyEmployee();
        $engineer1->setName('Roman B.');
        $engineer1->setDepartment('IT');
        $engineer1->setSalary(100);
        $this->engineers[] = $engineer1;

        $engineer2 = new CompanyEmployee();
        $engineer2->setName('Jonathan W.');
        $engineer2->setDepartment('IT');
        $engineer2->setSalary(100);
        $this->engineers[] = $engineer2;

        $engineer3 = new CompanyEmployee();
        $engineer3->setName('Benjamin E.');
        $engineer3->setDepartment('IT');
        $engineer3->setSalary(100);
        $this->engineers[] = $engineer3;

        $engineer4 = new CompanyEmployee();
        $engineer4->setName('Guilherme B.');
        $engineer4->setDepartment('IT');
        $engineer4->setSalary(100);
        $this->engineers[] = $engineer4;

        $this->_em->persist($this->salesPerson);
        $this->_em->persist($engineer1);
        $this->_em->persist($engineer2);
        $this->_em->persist($engineer3);
        $this->_em->persist($engineer4);
    }

    public function loadFullFixture()
    {
        $this->persistRelatedEmployees();

        $this->fix = new CompanyFixContract();
        $this->fix->setFixPrice(1000);
        $this->fix->setSalesPerson($this->salesPerson);
        $this->fix->addEngineer($this->engineers[0]);
        $this->fix->addEngineer($this->engineers[1]);
        $this->fix->markCompleted();

        $this->flex = new CompanyFlexContract();
        $this->flex->setSalesPerson($this->salesPerson);
        $this->flex->setHoursWorked(100);
        $this->flex->setPricePerHour(100);
        $this->flex->addEngineer($this->engineers[2]);
        $this->flex->addEngineer($this->engineers[1]);
        $this->flex->addEngineer($this->engineers[3]);
        $this->flex->markCompleted();

        $this->ultra = new CompanyFlexUltraContract();
        $this->ultra->setSalesPerson($this->salesPerson);
        $this->ultra->setHoursWorked(150);
        $this->ultra->setPricePerHour(150);
        $this->ultra->setMaxPrice(7000);
        $this->ultra->addEngineer($this->engineers[3]);
        $this->ultra->addEngineer($this->engineers[0]);

        $this->_em->persist($this->fix);
        $this->_em->persist($this->flex);
        $this->_em->persist($this->ultra);
        $this->_em->flush();
        $this->_em->clear();
    }

    public function testPersistChildOfBaseClass()
    {
        $this->persistRelatedEmployees();

        $fixContract = new CompanyFixContract();
        $fixContract->setFixPrice(1000);
        $fixContract->setSalesPerson($this->salesPerson);

        $this->_em->persist($fixContract);
        $this->_em->flush();
        $this->_em->clear();

        $contract = $this->_em->find(CompanyFixContract::class, $fixContract->getId());

        self::assertInstanceOf(CompanyFixContract::class, $contract);
        self::assertEquals(1000, $contract->getFixPrice());
        self::assertEquals($this->salesPerson->getId(), $contract->getSalesPerson()->getId());
    }

    public function testPersistDeepChildOfBaseClass()
    {
        $this->persistRelatedEmployees();

        $ultraContract = new CompanyFlexUltraContract();
        $ultraContract->setSalesPerson($this->salesPerson);
        $ultraContract->setHoursWorked(100);
        $ultraContract->setPricePerHour(50);
        $ultraContract->setMaxPrice(7000);

        $this->_em->persist($ultraContract);
        $this->_em->flush();
        $this->_em->clear();

        $contract = $this->_em->find(CompanyFlexUltraContract::class, $ultraContract->getId());

        self::assertInstanceOf(CompanyFlexUltraContract::class, $contract);
        self::assertEquals(7000, $contract->getMaxPrice());
        self::assertEquals(100, $contract->getHoursWorked());
        self::assertEquals(50, $contract->getPricePerHour());
    }

    public function testChildClassLifecycleUpdate()
    {
        $this->loadFullFixture();

        $fix = $this->_em->find(CompanyContract::class, $this->fix->getId());
        $fix->setFixPrice(2500);

        $this->_em->flush();
        $this->_em->clear();

        $newFix = $this->_em->find(CompanyContract::class, $this->fix->getId());
        self::assertEquals(2500, $newFix->getFixPrice());
    }

    public function testChildClassLifecycleRemove()
    {
        $this->loadFullFixture();

        $fix = $this->_em->find(CompanyContract::class, $this->fix->getId());
        $this->_em->remove($fix);
        $this->_em->flush();

        self::assertNull($this->_em->find(CompanyContract::class, $this->fix->getId()));
    }

    public function testFindAllForAbstractBaseClass()
    {
        $this->loadFullFixture();
        $contracts = $this->_em->getRepository(CompanyContract::class)->findAll();

        self::assertEquals(3, count($contracts));
        self::assertContainsOnly(CompanyContract::class, $contracts);
    }

    public function testFindAllForChildClass()
    {
        $this->loadFullFixture();

        self::assertEquals(1, count($this->_em->getRepository(CompanyFixContract::class)->findAll()));
        self::assertEquals(2, count($this->_em->getRepository(CompanyFlexContract::class)->findAll()));
        self::assertEquals(1, count($this->_em->getRepository(CompanyFlexUltraContract::class)->findAll()));
    }

    public function testFindForAbstractBaseClass()
    {
        $this->loadFullFixture();

        $contract = $this->_em->find(CompanyContract::class, $this->fix->getId());

        self::assertInstanceOf(CompanyFixContract::class, $contract);
        self::assertEquals(1000, $contract->getFixPrice());
    }

    public function testQueryForAbstractBaseClass()
    {
        $this->loadFullFixture();

        $contracts = $this->_em->createQuery('SELECT c FROM Doctrine\Tests\Models\Company\CompanyContract c')->getResult();

        self::assertEquals(3, count($contracts));
        self::assertContainsOnly(CompanyContract::class, $contracts);
    }

    public function testQueryForChildClass()
    {
        $this->loadFullFixture();

        self::assertEquals(1, count($this->_em->createQuery('SELECT c FROM Doctrine\Tests\Models\Company\CompanyFixContract c')->getResult()));
        self::assertEquals(2, count($this->_em->createQuery('SELECT c FROM Doctrine\Tests\Models\Company\CompanyFlexContract c')->getResult()));
        self::assertEquals(1, count($this->_em->createQuery('SELECT c FROM Doctrine\Tests\Models\Company\CompanyFlexUltraContract c')->getResult()));
    }

    public function testQueryBaseClassWithJoin()
    {
        $this->loadFullFixture();

        $contracts = $this->_em->createQuery('SELECT c, p FROM Doctrine\Tests\Models\Company\CompanyContract c JOIN c.salesPerson p')->getResult();
        self::assertEquals(3, count($contracts));
        self::assertContainsOnly(CompanyContract::class, $contracts);
    }

    public function testQueryScalarWithDiscriminatorValue()
    {
        $this->loadFullFixture();

        $contracts = $this->_em->createQuery('SELECT c FROM Doctrine\Tests\Models\Company\CompanyContract c ORDER BY c.id')->getScalarResult();

        $discrValues = \array_map(function($a) {
            return $a['c_discr'];
        }, $contracts);

        sort($discrValues);

        self::assertEquals(['fix', 'flexible', 'flexultra'], $discrValues);
    }

    public function testQueryChildClassWithCondition()
    {
        $this->loadFullFixture();

        $dql = 'SELECT c FROM Doctrine\Tests\Models\Company\CompanyFixContract c WHERE c.fixPrice = ?1';
        $contract = $this->_em->createQuery($dql)->setParameter(1, 1000)->getSingleResult();

        self::assertInstanceOf(CompanyFixContract::class, $contract);
        self::assertEquals(1000, $contract->getFixPrice());
    }

    /**
     * @group non-cacheable
     */
    public function testUpdateChildClassWithCondition()
    {
        $this->loadFullFixture();

        $dql = 'UPDATE Doctrine\Tests\Models\Company\CompanyFlexContract c SET c.hoursWorked = c.hoursWorked * 2 WHERE c.hoursWorked = 150';
        $affected = $this->_em->createQuery($dql)->execute();

        self::assertEquals(1, $affected);

        $flexContract = $this->_em->find(CompanyContract::class, $this->flex->getId());
        $ultraContract = $this->_em->find(CompanyContract::class, $this->ultra->getId());

        self::assertEquals(300, $ultraContract->getHoursWorked());
        self::assertEquals(100, $flexContract->getHoursWorked());
    }

    public function testUpdateBaseClassWithCondition()
    {
        $this->loadFullFixture();

        $dql = 'UPDATE Doctrine\Tests\Models\Company\CompanyContract c SET c.completed = true WHERE c.completed = false';
        $affected = $this->_em->createQuery($dql)->execute();

        self::assertEquals(1, $affected);

        $dql = 'UPDATE Doctrine\Tests\Models\Company\CompanyContract c SET c.completed = false';
        $affected = $this->_em->createQuery($dql)->execute();

        self::assertEquals(3, $affected);
    }

    public function testDeleteByChildClassCondition()
    {
        $this->loadFullFixture();

        $dql = 'DELETE Doctrine\Tests\Models\Company\CompanyFlexContract c';
        $affected = $this->_em->createQuery($dql)->execute();

        self::assertEquals(2, $affected);
    }

    public function testDeleteByBaseClassCondition()
    {
        $this->loadFullFixture();

        $dql = "DELETE Doctrine\Tests\Models\Company\CompanyContract c WHERE c.completed = true";
        $affected = $this->_em->createQuery($dql)->execute();

        self::assertEquals(2, $affected);

        $contracts = $this->_em->createQuery('SELECT c FROM Doctrine\Tests\Models\Company\CompanyContract c')->getResult();
        self::assertEquals(1, count($contracts));

        self::assertFalse($contracts[0]->isCompleted(), "Only non completed contracts should be left.");
    }

    /**
     * @group DDC-130
     */
    public function testDeleteJoinTableRecords()
    {
        $this->loadFullFixture();

        // remove managed copy of the fix contract
        $this->_em->remove($this->_em->find(get_class($this->fix), $this->fix->getId()));
        $this->_em->flush();

        self::assertNull($this->_em->find(get_class($this->fix), $this->fix->getId()), "Contract should not be present in the database anymore.");
    }

    /**
     * @group DDC-817
     */
    public function testFindByAssociation()
    {
        $this->loadFullFixture();

        $repos = $this->_em->getRepository(CompanyContract::class);
        $contracts = $repos->findBy(['salesPerson' => $this->salesPerson->getId()]);
        self::assertEquals(3, count($contracts), "There should be 3 entities related to " . $this->salesPerson->getId() . " for 'Doctrine\Tests\Models\Company\CompanyContract'");

        $repos = $this->_em->getRepository(CompanyFixContract::class);
        $contracts = $repos->findBy(['salesPerson' => $this->salesPerson->getId()]);
        self::assertEquals(1, count($contracts), "There should be 1 entities related to " . $this->salesPerson->getId() . " for 'Doctrine\Tests\Models\Company\CompanyFixContract'");

        $repos = $this->_em->getRepository(CompanyFlexContract::class);
        $contracts = $repos->findBy(['salesPerson' => $this->salesPerson->getId()]);
        self::assertEquals(2, count($contracts), "There should be 2 entities related to " . $this->salesPerson->getId() . " for 'Doctrine\Tests\Models\Company\CompanyFlexContract'");

        $repos = $this->_em->getRepository(CompanyFlexUltraContract::class);
        $contracts = $repos->findBy(['salesPerson' => $this->salesPerson->getId()]);
        self::assertEquals(1, count($contracts), "There should be 1 entities related to " . $this->salesPerson->getId() . " for 'Doctrine\Tests\Models\Company\CompanyFlexUltraContract'");
    }

    /**
     * @group DDC-1637
     */
    public function testInheritanceMatching()
    {
        $this->loadFullFixture();

        $repository = $this->_em->getRepository(CompanyContract::class);
        $contracts = $repository->matching(new Criteria(
            Criteria::expr()->eq('salesPerson', $this->salesPerson)
        ));
        self::assertEquals(3, count($contracts));

        $repository = $this->_em->getRepository(CompanyFixContract::class);
        $contracts = $repository->matching(new Criteria(
            Criteria::expr()->eq('salesPerson', $this->salesPerson)
        ));
        self::assertEquals(1, count($contracts));
    }

    /**
     * @group DDC-2430
     */
    public function testMatchingNonObjectOnAssocationThrowsException()
    {
        $this->loadFullFixture();

        $repository = $this->_em->getRepository(CompanyContract::class);

        $this->expectException(PersisterException::class);
        $this->expectExceptionMessage('annot match on Doctrine\Tests\Models\Company\CompanyContract::salesPerson with a non-object value.');

        $contracts = $repository->matching(new Criteria(
            Criteria::expr()->eq('salesPerson', $this->salesPerson->getId())
        ));

        // Load the association because it's wrapped in a lazy collection
        $contracts->toArray();
    }

    /**
     * @group DDC-834
     */
    public function testGetReferenceEntityWithSubclasses()
    {
        $this->loadFullFixture();

        $ref = $this->_em->getReference(CompanyContract::class, $this->fix->getId());
        self::assertNotInstanceOf(Proxy::class, $ref, "Cannot Request a proxy from a class that has subclasses.");
        self::assertInstanceOf(CompanyContract::class, $ref);
        self::assertInstanceOf(CompanyFixContract::class, $ref, "Direct fetch of the reference has to load the child class Employee directly.");
        $this->_em->clear();

        $ref = $this->_em->getReference(CompanyFixContract::class, $this->fix->getId());
        self::assertInstanceOf(Proxy::class, $ref, "A proxy can be generated only if no subclasses exists for the requested reference.");
    }

    /**
     * @group DDC-952
     */
    public function testEagerLoadInheritanceHierarchy()
    {
        $this->loadFullFixture();

        $dql = 'SELECT f FROM Doctrine\Tests\Models\Company\CompanyFixContract f WHERE f.id = ?1';
        $contract = $this->_em
            ->createQuery($dql)
            ->setFetchMode(CompanyFixContract::class, 'salesPerson', FetchMode::EAGER)
            ->setParameter(1, $this->fix->getId())
            ->getSingleResult();

        self::assertNotInstanceOf(Proxy::class, $contract->getSalesPerson());
    }
}
