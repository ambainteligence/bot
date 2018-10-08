<?php

namespace App\Repository;

use App\Entity\BackProfitMonth;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method BackProfitMonth|null find($id, $lockMode = null, $lockVersion = null)
 * @method BackProfitMonth|null findOneBy(array $criteria, array $orderBy = null)
 * @method BackProfitMonth[]    findAll()
 * @method BackProfitMonth[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BackProfitMonthRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, BackProfitMonth::class);
    }

//    /**
//     * @return BackProfitMonth[] Returns an array of BackProfitMonth objects
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
    public function findOneBySomeField($value): ?BackProfitMonth
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
