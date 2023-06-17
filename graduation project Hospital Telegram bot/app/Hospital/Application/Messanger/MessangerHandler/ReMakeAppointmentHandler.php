<?php

declare(strict_types=1);

namespace App\Hospital\Application\Messanger\MessangerHandler;

use App\Hospital\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Hospital\Domain\Appointment\Exception\AppointmentPartNotFoundException;
use App\Hospital\Domain\Appointment\Exception\AppointmentPartSaveFailedException;
use App\Hospital\Domain\Appointment\Interface\AppointmentListInterface;
use App\Hospital\Domain\Appointment\Interface\ReMakeAppointmentInterface;
use App\Hospital\Domain\Client\Client;
use App\Hospital\Domain\Messanger\Interface\Keyboard\KeyboardBuilderInterface;
use App\Hospital\Domain\Messanger\Interface\Keyboard\KeyboardType;
use App\Hospital\Domain\Messanger\Interface\KeyboardButton\KeyboardButtonBuilderInterface;
use App\Hospital\Domain\Messanger\Interface\KeyboardButton\KeyboardButtonCallbackBuilderInterface;
use App\Hospital\Domain\Messanger\Interface\KeyboardButton\KeyboardButtonInterface;
use App\Hospital\Domain\Messanger\Interface\MessangerHandlerInterface;
use App\Hospital\Domain\Messanger\Interface\MessangerHandlerRequestInterface;
use App\Hospital\Domain\Messanger\Interface\MessangerInterface;
use App\Hospital\Domain\Messanger\MessangerCommand;
use Psr\Log\LoggerInterface;

class ReMakeAppointmentHandler implements MessangerHandlerInterface
{
    public function __construct(
        protected LoggerInterface                        $logger,
        protected KeyboardBuilderInterface               $keyboardBuilder,
        protected KeyboardButtonBuilderInterface         $buttonBuilder,
        protected KeyboardButtonCallbackBuilderInterface $callbackBuilder,
        protected AppointmentListInterface               $appointmentList,
        protected ReMakeAppointmentInterface             $reMakeAppointment
    ) {
    }

    public function handler(
        Client                           $client,
        MessangerHandlerRequestInterface $request,
        MessangerInterface               $messanger
    ): void {
        $messanger->editMessage();

        $callbackData = $request->getCallbackData();
        $appointmentId = $callbackData->getValue('appointment_id');

        try {
            if ($appointmentId) {
                $appointment = $this->appointmentList->getAppointmentById($appointmentId);

                $this->reMakeAppointment->saveAppointment($client, $appointmentId);
                $this->reMakeAppointment->fillAppointmentPart($client, $appointment);
            } else if (!$this->reMakeAppointment->hasAppointment($client)) {
                $messanger->setMessage('Произошла ошибка, попробуйте позднее');
                return;
            }

            $buttons = $this->getDateButtons($client);

            $keyboard = $this->keyboardBuilder->makeInlineKeyboard();

            if ($buttons) {
                $messanger->setMessage('Выберите дату записи');

                foreach ($buttons as $button) {
                    $keyboard->addRow($button);
                }
            } else {
                $messanger->setMessage('В ближайшее время специалист не работает');
            }

            $messanger->setMessangerKeyboard($keyboard, KeyboardType::Inline);
        } catch (AppointmentNotFoundException) {
            $messanger->setMessage('Запись не найдена');
        } catch (AppointmentPartSaveFailedException|AppointmentPartNotFoundException) {
            $messanger->setMessage('Произошла ошибка, попробуйте позднее');
        }
    }

    /**
     * @return KeyboardButtonInterface[]
     * @throws AppointmentPartNotFoundException
     */
    protected function getDateButtons(Client $client): array
    {
        $buttons = [];
        $dates = $this->reMakeAppointment->getDates($client);

        foreach ($dates as $date) {
            $callbackData = $this->callbackBuilder
                ->setAction(MessangerCommand::ReMakeAppointmentChooseDateAction)
                ->setCallbackData(['date' => $date->format('Y-m-d')])
                ->make();

            $buttons[] = $this->buttonBuilder
                ->setText($date->format('d.m.Y'))
                ->setCallbackData($callbackData)
                ->makeInlineButton();
        }

        return $buttons;
    }
}
