<?php

namespace App\Http\Controllers\Admin\Ticket;

use App\Enums\Common\Status;
use App\Enums\DefaultTemplateSlug;
use App\Enums\StatusEnum;
use App\Enums\System\ChannelTypeEnum;
use App\Traits\Manageable;
use Illuminate\View\View;
use App\Models\SupportFile;
use Illuminate\Http\Request;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
use App\Http\Utility\SendMail;
use App\Enums\TicketStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Models\Template;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SupportTicketController extends Controller
{
    use Manageable;

    protected $sendMail;

    /**
     * __construct
     *
     */
    public function __construct()
    {
        $this->sendMail = new SendMail();
    }

    /**
     * @return View
     */
    public function index(): View {

        Session::put("menu_active", true);
        $title          = translate("Manage Support ticket");
        $supportTickets = SupportTicket::search(['name', 'email', 'subject'])
                                            ->latest()
                                            ->with('user')
                                            ->routefilter()
                                            ->paginate(paginateNumber(site_settings("paginate_number")))
                                            ->appends(request()->all());
        return view('admin.support_ticket.index', compact('title', 'supportTickets'));
    }

    /**
     * 
     * @param int $id
     * 
     * @return \Illuminate\Http\RedirectResponse
     */
    public function ticketDetails(int $id): View {

        $title         = "Support ticket reply";
        $supportTicket = SupportTicket::with('messages')->findOrFail($id);
        return view('admin.support_ticket.details', compact('title', 'supportTicket'));
    }

    /**
     *
     * @param Request $request
     * 
     * @param int $id
     * 
     * @return \Illuminate\Http\RedirectResponse
     */
    public function ticketReply(Request $request, $id) {

        $status  = 'success';
        $notifyMessage = translate("Support ticket replied successfully");

        try {
            
            $supportTicket = SupportTicket::where('id', $id)->first();
            if($supportTicket) {

                $supportTicket->status = TicketStatusEnum::ANSWERED->value;
                $supportTicket->save();
    
                $message                    = new SupportMessage();
                $message->support_ticket_id = $supportTicket->id;
                $message->admin_id          = Auth::guard('admin')->id();
                $message->message           = $request->input('message');
                $message->save();
    
                if ($request->hasFile('file')) {
                    foreach ($request->file('file') as $file) {
                        $supportFile                     = new SupportFile();
                        $supportFile->support_message_id = $message->id;
                        $supportFile->file               = uploadNewFile($file, filePath()['ticket']['path']);
                        $supportFile->save();
                    }
                }

                // $mailCode must be defined for BOTH paths — the notification email
                // fires whether or not files were attached. Previously it lived inside
                // the file-upload block, so replies without attachments (the common
                // case) tried to send with an undefined $mailCode and silently failed.
                $mailCode = [
                    'ticket_number' => $supportTicket->ticket_number,
                    'link'          => route('user.ticket.detail', $supportTicket->id),
                ];
                
                $gateway = $this->getSpecificLogByColumn(
                    model: new Gateway(), 
                    column: "is_default",
                    value: StatusEnum::TRUE->status(),
                    attributes: [
                        "user_id" => null,
                        "channel" => ChannelTypeEnum::EMAIL->value,
                    ]
                );
                
                $template = $this->getSpecificLogByColumn(
                    model: new Template(), 
                    column: "slug",
                    value: DefaultTemplateSlug::SUPPORT_TICKET_REPLY->value,
                    attributes: [
                        "user_id" => null,
                        "channel" => ChannelTypeEnum::EMAIL->value,
                        "default" => true,
                        "status"  => Status::ACTIVE->value
                    ]
                );
                
                if($gateway && $template)$this->sendMail->MailNotification($gateway, $template, $supportTicket->user, $mailCode);
           
            } else {

                $status  = 'error';
                $notifyMessage = translate("Something went wrong while fetching new tickets");
            }
        } catch (\Exception $e) {
            
            $status  = 'error';
            $notifyMessage = translate("Server Error: ") . $e->getMessage();
        }


        $notify[] = [$status, $notifyMessage];
        return back()->withNotify($notify);
    }

    /**
     * 
     * @param int $id
     * 
     * @return \Illuminate\Http\RedirectResponse
     */
    public function closedTicket($id) {

        $status  = 'success';
        $message = translate("Support ticket has been closed");

        try {

            $supportTicket = SupportTicket::where('id',$id)->first();
            if($supportTicket) {

                $supportTicket->status = TicketStatusEnum::CLOSED->value;
                $supportTicket->save();
            } else {

                $status  = 'error';
                $message = translate("Something went wrong while fetching new tickets");
            }
            

        } catch (\Exception $e) {

            $status  = 'error';
            $message = translate("Server Error: ") . $e->getMessage();
        }
        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }

    public function supportTicketDownload($id) {

        try {
            $supportFile = SupportFile::where('id',decrypt($id))->first();
        
            if($supportFile) {

                $file = $supportFile->file;
                $path = filePath()['ticket']['path'].'/'.$file;
                $title = slug('file').'-'.$file;
                $mimetype = mime_content_type($path);
                header('Content-Disposition: attachment; filename="' . $title);
                header("Content-Type: " . $mimetype);
                return readfile($path);
            }
        } catch(\Exception $e) {
            
            $notify[] = [ 'error', translate("Server Error: ") . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }
}
