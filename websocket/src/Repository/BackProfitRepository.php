<?php

namespace App\Repository;

use App\Entity\BackProfit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method BackProfit|null find($id, $lockMode = null, $lockVersion = null)
 * @method BackProfit|null findOneBy(array $criteria, array $orderBy = null)
 * @method BackProfit[]    findAll()
 * @method BackProfit[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BackProfitRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, BackProfit::class);
    }

//    /**
//     * @return BackProfit[] Returns an array of BackProfit objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?BackProfit
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
